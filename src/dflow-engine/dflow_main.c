#define _GNU_SOURCE
#include <stdio.h>
#include <stdlib.h>
#include <pcap.h>
#include <netinet/ip.h>
#include <netinet/ip6.h>
#include <netinet/tcp.h>
#include <netinet/udp.h>
#include <netinet/if_ether.h>
#include <arpa/inet.h>
#include <time.h>
#include <pthread.h>
#include <sched.h>
#include <unistd.h>
#include <sys/socket.h>
#include <linux/if_packet.h>
#include <net/if.h>
#include "dflow_state.h"
#include "dflow_l7.h"

// Capture Modes
typedef enum {
    MODE_PCAP,
    MODE_AF_PACKET,
    MODE_DPDK,
    MODE_SNMP_CORRELATION
} capture_mode_t;

capture_mode_t g_mode = MODE_PCAP;
char *g_interface = "eth0";
char *g_log_dir = ".";

#define MAX_THREADS 8

typedef struct {
    char *interface;
    int thread_id;
    int cpu_core;
    dflow_state_ctx_t *ctx;
} thread_args_t;

uint32_t g_flow_timeout = 300;
uint32_t g_flush_interval = 60;
pthread_mutex_t g_flush_mutex = PTHREAD_MUTEX_INITIALIZER;

void flush_to_db(dflow_state_ctx_t *ctx, time_t *last_flush) {
    char flow_path[512], metrics_path[512];
    // Use thread-specific files to avoid lock contention
    snprintf(flow_path, sizeof(flow_path), "%s/dflow_pending_flows_t%d.log", g_log_dir, ctx->thread_id);
    snprintf(metrics_path, sizeof(metrics_path), "%s/dflow_metrics_t%d.log", g_log_dir, ctx->thread_id);

    FILE *f = fopen(flow_path, "a");
    if (!f) return;

    time_t now = time(NULL);
    for (int i = 0; i < HASH_SIZE; i++) {
        dflow_session_t *s = ctx->table[i];
        while (s) {
            // Only flush sessions that had activity since last flush
            if (s->last_seen > *last_flush) {
                char src_ip[64], dst_ip[64], src_mac[18], dst_mac[18];
                if (s->key.is_ipv6) {
                    inet_ntop(AF_INET6, s->key.src_ip.v6, src_ip, sizeof(src_ip));
                    inet_ntop(AF_INET6, s->key.dst_ip.v6, dst_ip, sizeof(dst_ip));
                } else {
                    struct in_addr addr;
                    addr.s_addr = s->key.src_ip.v4;
                    strcpy(src_ip, inet_ntoa(addr));
                    addr.s_addr = s->key.dst_ip.v4;
                    strcpy(dst_ip, inet_ntoa(addr));
                }

                sprintf(src_mac, "%02x:%02x:%02x:%02x:%02x:%02x", 
                    s->src_mac[0], s->src_mac[1], s->src_mac[2], s->src_mac[3], s->src_mac[4], s->src_mac[5]);
                sprintf(dst_mac, "%02x:%02x:%02x:%02x:%02x:%02x", 
                    s->dst_mac[0], s->dst_mac[1], s->dst_mac[2], s->dst_mac[3], s->dst_mac[4], s->dst_mac[5]);

                fprintf(f, "%ld|%s|%s|%d|%d|%d|%ld|%d|%s|%s|%s|%s|%s|%s|%s|%d|%d|%.2f|0x%04x|%d\n",
                    now, src_ip, dst_ip, s->key.src_port, s->key.dst_port,
                    s->key.protocol, s->bytes_in + s->bytes_out,
                    s->pkts_in + s->pkts_out, s->l7_proto, s->sni, s->ja3,
                    s->anomaly_type, s->cve_id, src_mac, dst_mac, s->vlan,
                    s->tcp_flags, s->rtt_ms, s->eth_type, s->pcp);
            }
            s = s->next;
        }
    }
    
    // Write metrics
    FILE *mf = fopen(metrics_path, "a");
    if (mf) {
        fprintf(mf, "%ld|%d|%ld|%ld|%ld|%ld|%ld|%ld\n",
            now, ctx->thread_id, ctx->metrics.processed_packets, ctx->metrics.processed_bytes,
            ctx->metrics.dropped_packets, ctx->metrics.active_sessions, 
            ctx->metrics.total_flows, ctx->metrics.hash_collisions);
        fclose(mf);
    }

    fclose(f);
    *last_flush = now;
}

void packet_handler(u_char *args, const struct pcap_pkthdr *header, const u_char *packet);

#include <sys/mman.h>
#include <poll.h>

// AF_PACKET TPACKET_V3 Structures
#define BLOCK_SIZE (1024 * 1024) // 1MB blocks
#define FRAME_SIZE 2048
#define BLOCK_NR 128 // 128MB total ring buffer per thread

// AF_PACKET Capture implementation (TPACKET_V3 - Zero Copy)
void *run_af_packet(void *arg) {
    thread_args_t *targs = (thread_args_t *)arg;
    int sock = socket(AF_PACKET, SOCK_RAW, htons(ETH_P_ALL));
    if (sock < 0) {
        perror("socket");
        return NULL;
    }

    int ver = TPACKET_V3;
    if (setsockopt(sock, SOL_PACKET, PACKET_VERSION, &ver, sizeof(ver)) < 0) {
        perror("setsockopt PACKET_VERSION");
        close(sock);
        return NULL;
    }

    struct tpacket_req3 req;
    memset(&req, 0, sizeof(req));
    req.tp_block_size = BLOCK_SIZE;
    req.tp_frame_size = FRAME_SIZE;
    req.tp_block_nr = BLOCK_NR;
    req.tp_frame_nr = (BLOCK_SIZE * BLOCK_NR) / FRAME_SIZE;
    req.tp_retire_blk_tov = 60; // Timeout in msec
    req.tp_feature_req_word = TP_FT_REQ_FILL_RXHASH;

    if (setsockopt(sock, SOL_PACKET, PACKET_RX_RING, &req, sizeof(req)) < 0) {
        perror("setsockopt PACKET_RX_RING");
        close(sock);
        return NULL;
    }

    uint8_t *mapped = mmap(NULL, req.tp_block_size * req.tp_block_nr,
                           PROT_READ | PROT_WRITE, MAP_SHARED, sock, 0);
    if (mapped == MAP_FAILED) {
        perror("mmap");
        close(sock);
        return NULL;
    }

    struct sockaddr_ll sa;
    memset(&sa, 0, sizeof(sa));
    sa.sll_family = AF_PACKET;
    sa.sll_protocol = htons(ETH_P_ALL);
    sa.sll_ifindex = if_nametoindex(targs->interface);

    if (bind(sock, (struct sockaddr *)&sa, sizeof(sa)) < 0) {
        perror("bind");
        munmap(mapped, req.tp_block_size * req.tp_block_nr);
        close(sock);
        return NULL;
    }

    struct iovec *ring = malloc(req.tp_block_nr * sizeof(struct iovec));
    for (int i = 0; i < req.tp_block_nr; ++i) {
        ring[i].iov_base = mapped + (i * req.tp_block_size);
        ring[i].iov_len = req.tp_block_size;
    }

    unsigned int block_num = 0;
    struct pollfd pfd;
    memset(&pfd, 0, sizeof(pfd));
    pfd.fd = sock;
    pfd.events = POLLIN | POLLERR;

    printf("Thread %d: AF_PACKET V3 Zero-Copy listening on %s (Core %d)\n", 
           targs->thread_id, targs->interface, targs->cpu_core);

    while (1) {
        struct tpacket_block_desc *bd = (struct tpacket_block_desc *)ring[block_num].iov_base;

        if ((bd->hdr.bh1.block_status & TP_STATUS_USER) == 0) {
            poll(&pfd, 1, -1);
            continue;
        }

        struct tpacket3_hdr *ppd;
        int num_pkts = bd->hdr.bh1.num_pkts;
        ppd = (struct tpacket3_hdr *)((uint8_t *)bd + bd->hdr.bh1.offset_to_first_pkt);

        for (int i = 0; i < num_pkts; ++i) {
            struct pcap_pkthdr header;
            header.caplen = ppd->tp_snaplen;
            header.len = ppd->tp_len;
            header.ts.tv_sec = ppd->tp_sec;
            header.ts.tv_usec = ppd->tp_usec;

            uint8_t *packet = (uint8_t *)ppd + ppd->tp_mac;
            packet_handler((u_char *)targs, &header, packet);

            ppd = (struct tpacket3_hdr *)((uint8_t *)ppd + ppd->tp_next_offset);
        }

        bd->hdr.bh1.block_status = TP_STATUS_KERNEL;
        block_num = (block_num + 1) % req.tp_block_nr;
    }

    return NULL;
}

// DPDK Capture implementation (Skeleton)
// In a real scenario, this would use rte_eal_init, rte_eth_rx_burst, etc.
void *run_dpdk(void *arg) {
    printf("DPDK mode initiated. Waiting for EAL environment...\n");
    // Placeholder for DPDK logic
    while(1) { sleep(10); }
    return NULL;
}

#include <stdatomic.h>

// Metrics should use atomic types for cross-thread consistency if needed, 
// though here they are sharded per thread.
// We'll focus on the core processing loop performance.

void packet_handler(u_char *args, const struct pcap_pkthdr *header, const u_char *packet) {
    thread_args_t *targs = (thread_args_t *)args;
    dflow_state_ctx_t *ctx = targs->ctx;
    static __thread time_t last_flush = 0;
    if (last_flush == 0) last_flush = time(NULL);

    struct ethhdr *eth = (struct ethhdr *)packet;
    uint16_t eth_proto = ntohs(eth->h_proto);
    uint32_t l3_offset = 14;
    uint16_t vlan = 0;
    uint8_t pcp = 0;

    // Handle VLAN tag (802.1Q) - Branch prediction friendly
    if (__builtin_expect(eth_proto == 0x8100, 0)) {
        uint16_t tci = ntohs(*(uint16_t *)(packet + 14));
        vlan = tci & 0x0FFF;
        pcp = (tci >> 13) & 0x07;
        eth_proto = ntohs(*(uint16_t *)(packet + 16));
        l3_offset = 18;
    }
    // Handle QinQ (802.1ad)
    else if (__builtin_expect(eth_proto == 0x88a8, 0)) {
        eth_proto = ntohs(*(uint16_t *)(packet + 18));
        l3_offset = 22; // Outer + Inner VLAN
    }

    // Passively detect LLDP (0x88cc) or CDP (0x2000)
    if (__builtin_expect(eth_proto == 0x88cc || eth_proto == 0x2000, 0)) {
        ctx->metrics.processed_packets++;
        return;
    }

    dflow_key_t key = {0};
    const uint8_t *payload = NULL;
    uint32_t payload_len = 0;

    // Optimized L3 Parsing
    if (__builtin_expect(eth_proto == ETH_P_IP, 1)) {
        struct ip *ip = (struct ip *)(packet + l3_offset);
        key.is_ipv6 = false;
        key.src_ip.v4 = ip->ip_src.s_addr;
        key.dst_ip.v4 = ip->ip_dst.s_addr;
        key.protocol = ip->ip_p;
        uint32_t ip_hl = ip->ip_hl << 2;
        payload = packet + l3_offset + ip_hl;
        payload_len = header->len - (l3_offset + ip_hl);
    } else if (eth_proto == ETH_P_IPV6) {
        struct ip6_hdr *ip6 = (struct ip6_hdr *)(packet + l3_offset);
        key.is_ipv6 = true;
        memcpy(key.src_ip.v6, &ip6->ip6_src, 16);
        memcpy(key.dst_ip.v6, &ip6->ip6_dst, 16);
        key.protocol = ip6->ip6_nxt;
        payload = packet + l3_offset + 40;
        payload_len = header->len - (l3_offset + 40);
    } else {
        ctx->metrics.dropped_packets++;
        return;
    }

    // Optimized L4 Parsing
    if (__builtin_expect(key.protocol == IPPROTO_TCP, 1)) {
        if (__builtin_expect(payload_len < sizeof(struct tcphdr), 0)) return;
        struct tcphdr *tcp = (struct tcphdr *)payload;
        key.src_port = ntohs(tcp->source);
        key.dst_port = ntohs(tcp->dest);
        uint32_t tcp_hl = (tcp->doff << 2);
        payload += tcp_hl;
        payload_len -= tcp_hl;
    } else if (key.protocol == IPPROTO_UDP) {
        if (__builtin_expect(payload_len < sizeof(struct udphdr), 0)) return;
        struct udphdr *udp = (struct udphdr *)payload;
        key.src_port = ntohs(udp->source);
        key.dst_port = ntohs(udp->dest);
        payload += sizeof(struct udphdr);
        payload_len -= sizeof(struct udphdr);
    } else {
        return;
    }

    ctx->metrics.processed_packets++;
    ctx->metrics.processed_bytes += header->len;

    // Sharded Hash Table access (Lock-free since sharded by thread)
    dflow_session_t *session = dflow_state_get_or_create(ctx, &key);
    if (__builtin_expect(session != NULL, 1)) {
        if (__builtin_expect(session->pkts_in == 0, 0)) { // New session
            memcpy(session->src_mac, eth->h_source, 6);
            memcpy(session->dst_mac, eth->h_dest, 6);
            session->vlan = vlan;
            session->eth_type = eth_proto;
            session->pcp = pcp;
            session->first_seen = time(NULL);
        }
        
        // Update TCP Flags and RTT Estimation
        if (key.protocol == IPPROTO_TCP) {
            struct tcphdr *tcp = (struct tcphdr *)(packet + l3_offset + (key.is_ipv6 ? 40 : ((struct ip *)(packet + l3_offset))->ip_hl << 2));
            session->tcp_flags |= tcp->th_flags;
            
            // Basic RTT estimation on SYN-ACK
            if (__builtin_expect((tcp->th_flags & (TH_SYN | TH_ACK)) == (TH_SYN | TH_ACK), 0)) {
                session->rtt_ms = (float)(header->ts.tv_sec - session->first_seen) * 1000.0;
            }
        }

        session->bytes_in += header->len;
        session->pkts_in++;
        session->last_seen = time(NULL);
        
        // Selective DPI (only first few packets with payload)
        if (__builtin_expect(payload_len > 0 && session->pkts_in < 10, 0)) {
            dflow_l7_inspect(session, payload, payload_len);
        }

        // Periodic maintenance
        time_t now = time(NULL);
        if (__builtin_expect(now - last_flush >= g_flush_interval, 0)) {
            dflow_state_gc(ctx, g_flow_timeout);
            flush_to_db(ctx, &last_flush);
        }
    }
}

void *worker_thread(void *arg) {
    thread_args_t *targs = (thread_args_t *)arg;
    
    // Pin thread to CPU core
    cpu_set_t cpuset;
    CPU_ZERO(&cpuset);
    CPU_SET(targs->cpu_core, &cpuset);
    pthread_setaffinity_np(pthread_self(), sizeof(cpu_set_t), &cpuset);

    if (g_mode == MODE_AF_PACKET) {
        printf("Worker %d: AF_PACKET mode active on %s\n", targs->thread_id, targs->interface);
        return run_af_packet(targs);
    } else if (g_mode == MODE_DPDK) {
        printf("Worker %d: DPDK mode active\n", targs->thread_id);
        return run_dpdk(targs);
    }

    // Default: PCAP
    char errbuf[PCAP_ERRBUF_SIZE];
    pcap_t *handle = pcap_open_live(targs->interface, BUFSIZ, 1, 100, errbuf);
    if (!handle) {
        fprintf(stderr, "Thread %d: Error opening %s: %s\n", targs->thread_id, targs->interface, errbuf);
        return NULL;
    }

    printf("Worker %d: libpcap mode active on %s (Core %d)\n", targs->thread_id, targs->interface, targs->cpu_core);
    pcap_loop(handle, 0, packet_handler, (u_char *)targs);
    
    pcap_close(handle);
    return NULL;
}

int main(int argc, char **argv) {
    if (argc < 2) {
        printf("Usage: %s <interface1,interface2,...> [mode: pcap|af_packet|dpdk] [log_dir] [timeout] [flush]\n", argv[0]);
        return 1;
    }

    char *interfaces = strdup(argv[1]);
    if (argc > 2) {
        if (strcmp(argv[2], "af_packet") == 0) g_mode = MODE_AF_PACKET;
        else if (strcmp(argv[2], "dpdk") == 0) g_mode = MODE_DPDK;
        else g_mode = MODE_PCAP;
    }
    if (argc > 3) g_log_dir = strdup(argv[3]);
    if (argc > 4) g_flow_timeout = atoi(argv[4]);
    if (argc > 5) g_flush_interval = atoi(argv[5]);

    printf("DFlow Engine starting (Mode: %s, LogDir: %s, Timeout: %ds, Flush: %ds)...\n", 
           g_mode == MODE_AF_PACKET ? "AF_PACKET" : (g_mode == MODE_DPDK ? "DPDK" : "libpcap"),
           g_log_dir, g_flow_timeout, g_flush_interval);

    pthread_t threads[MAX_THREADS];
    thread_args_t targs[MAX_THREADS];
    int thread_count = 0;

    char *iface = strtok(interfaces, ",");
    while (iface && thread_count < MAX_THREADS) {
        targs[thread_count].interface = strdup(iface);
        targs[thread_count].thread_id = thread_count;
        targs[thread_count].cpu_core = thread_count; // Simplified mapping
        targs[thread_count].ctx = dflow_state_init(thread_count, thread_count);
        
        pthread_create(&threads[thread_count], NULL, worker_thread, &targs[thread_count]);
        
        thread_count++;
        iface = strtok(NULL, ",");
    }

    for (int i = 0; i < thread_count; i++) {
        pthread_join(threads[i], NULL);
    }

    free(interfaces);
    return 0;
}
