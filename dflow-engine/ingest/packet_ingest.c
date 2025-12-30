#include <stdio.h>
#include <pcap.h>
#include "../include/dflow.h"

void packet_callback(u_char *args, const struct pcap_pkthdr *header, const u_char *packet) {
    dflow_engine_ctx_t *ctx = (dflow_engine_ctx_t *)args;
    
    ctx->total_packets++;
    ctx->total_bytes += header->len;

    /* 
     * L2/L3/L4 Parsing would happen here
     * - Extract VLAN (802.1Q)
     * - Extract IP Src/Dst
     * - Extract Ports
     */
}

void dflow_ingest_start(const char *interface) {
    char errbuf[PCAP_ERRBUF_SIZE];
    pcap_t *handle;

    printf("[INGEST] Starting capture on interface: %s\n", interface);

    handle = pcap_open_live(interface, BUFSIZ, 1, 1000, errbuf);
    if (handle == NULL) {
        fprintf(stderr, "[INGEST] Could not open device %s: %s\n", interface, errbuf);
        return;
    }

    // In a real implementation, this would run in its own thread
    // pcap_loop(handle, 0, packet_callback, NULL);
}
