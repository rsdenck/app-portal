#ifndef DFLOW_H
#define DFLOW_H

#include <stdint.h>
#include <time.h>
#include <pcap.h>

/* Versioning */
#define DFLOW_VERSION "3.0.0-native-c"

/* Limits */
#define MAX_IFACE_NAME 64
#define MAX_IP_STR 46
#define MAX_MAC_STR 18

/* Flow Structure - Core of the correlation */
typedef struct {
    uint32_t src_ip;
    uint32_t dst_ip;
    uint16_t src_port;
    uint16_t dst_port;
    uint8_t protocol;
    uint16_t vlan_id;
    
    uint64_t bytes;
    uint64_t packets;
    
    char l7_proto[32];
    char sni[256];
    
    struct timespec first_seen;
    struct timespec last_seen;
} dflow_record_t;

/* Interface Inventory */
typedef struct {
    char name[MAX_IFACE_NAME];
    uint16_t if_index;
    uint16_t vlan;
    uint64_t speed;
    char mac[MAX_MAC_STR];
    uint8_t status; // 1: up, 0: down
} dflow_interface_t;

/* Global Engine State */
typedef struct {
    int running;
    uint64_t total_packets;
    uint64_t total_bytes;
    uint32_t active_flows;
    char sensor_name[128];
} dflow_engine_ctx_t;

/* --- CORE --- */
void dflow_core_init(dflow_engine_ctx_t *ctx);
void dflow_config_load(const char *path);
uint64_t dflow_time_get_ms();
void dflow_snmp_init();
void dflow_snmp_poll_all();

/* --- INGEST --- */
void dflow_ingest_start(const char *interface);
void dflow_ingest_flow(void *data);

/* --- L2 --- */
void dflow_l2_vlan_process(uint16_t vlan_id);
void dflow_l2_update_mac(const char *mac, uint16_t vlan_id);
uint16_t dflow_l2_get_vlan(const char *mac);
void dflow_l2_topology_compute();

/* --- L3 --- */
void dflow_l3_update_ip(uint32_t ip, const char *mac, uint16_t vlan_id);
void dflow_l3_add_traffic(uint32_t ip, uint64_t bytes, int is_sent);
int dflow_l3_is_in_subnet(uint32_t ip, uint32_t network, uint32_t mask);
void dflow_l3_routing_infer();

/* --- L4 --- */
void dflow_l4_parse_tcp(const unsigned char *payload, dflow_record_t *rec);
void dflow_l4_port_analysis(uint16_t port);
void dflow_l4_service_discovery();
void dflow_l4_vuln_check();

/* --- L7 --- */
const char* dflow_l7_identify(uint16_t port);
void dflow_l7_deep_inspect(const unsigned char *payload, int len, dflow_record_t *rec);

/* --- ACTIVE --- */
void dflow_active_scheduler_tick();
void dflow_active_target_add(uint32_t ip);
void dflow_active_probe_tcp_syn(uint32_t ip, uint16_t port);
void dflow_active_probe_tcp_banner(uint32_t ip, uint16_t port);
void dflow_active_probe_tls_fp(uint32_t ip, uint16_t port);
void dflow_active_probe_http_fp(uint32_t ip, uint16_t port);
void dflow_active_probe_smb_fp(uint32_t ip, uint16_t port);
void dflow_active_results_merge();
void dflow_active_confidence_calc();

/* --- ANALYTICS --- */
void dflow_analytics_update_baseline(void *b, uint64_t current_pps, uint64_t current_bps);
void dflow_analytics_detect(uint64_t current, double avg, const char *type);
void dflow_analytics_score_calc();
void dflow_analytics_timeline_add();

/* --- EXPORT --- */
typedef struct {
    int fd;
    char socket_path[256];
    int connected;
} dflow_ipc_ctx_t;

void dflow_export_ipc_init(const char *path);
void dflow_export_ipc_send(const char *data, int len);
void dflow_export_ipc_close();
void dflow_export_netflow_send();
void dflow_export_json_write();

/* --- STORAGE --- */
void dflow_storage_write_json(const char *path, dflow_engine_ctx_t *ctx);
void dflow_storage_save_flow(dflow_record_t *rec);

#endif /* DFLOW_H */
