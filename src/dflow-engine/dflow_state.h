#ifndef DFLOW_STATE_H
#define DFLOW_STATE_H

#include <stdint.h>
#include <time.h>
#include <netinet/in.h>
#include <stdbool.h>

/**
 * 5-tuple structure for session identification (IPv4 & IPv6)
 */
typedef struct {
    union {
        uint32_t v4;
        uint8_t  v6[16];
    } src_ip;
    union {
        uint32_t v4;
        uint8_t  v6[16];
    } dst_ip;
    uint16_t src_port;
    uint16_t dst_port;
    uint8_t  protocol;
    bool     is_ipv6;
} dflow_key_t;

/**
 * Session state and metadata
 */
typedef struct dflow_session {
    dflow_key_t key;
    
    // L2 Metadata
    uint8_t src_mac[6];
    uint8_t dst_mac[6];
    uint16_t vlan;

    // Traffic stats
    uint64_t bytes_in;
    uint64_t bytes_out;
    uint32_t pkts_in;
    uint32_t pkts_out;
    
    // Telemetry & Advanced Metrics
    uint8_t  tcp_flags;
    float    rtt_ms;
    uint16_t eth_type;
    uint8_t  pcp;
    
    // Timing
    time_t first_seen;
    time_t last_seen;
    
    // TCP specific state (Sliding window / Flags)
    struct {
        uint32_t last_seq;
        uint32_t last_ack;
        uint8_t  last_flags;
        uint8_t  state; // SYN_SENT, ESTABLISHED, etc
    } tcp_state;

    // L7 Metadata
    char l7_proto[32];
    char sni[256];
    char ja3[33];
    
    // Anomaly Detection
    char anomaly_type[64];
    char cve_id[32];

    struct dflow_session *next; // For hash collision chaining
} dflow_session_t;

#define HASH_SIZE 65536

/**
 * Thread-local metrics for observability
 */
typedef struct {
    uint64_t processed_packets;
    uint64_t processed_bytes;
    uint64_t dropped_packets;
    uint64_t active_sessions;
    uint64_t total_flows;
    uint64_t hash_collisions;
} dflow_metrics_t;

/**
 * State Engine Context (Sharded per thread)
 */
typedef struct {
    dflow_session_t *table[HASH_SIZE];
    dflow_metrics_t metrics;
    int thread_id;
    int cpu_core;
} dflow_state_ctx_t;

// API Functions
dflow_state_ctx_t* dflow_state_init(int thread_id, int cpu_core);
dflow_session_t* dflow_state_get_or_create(dflow_state_ctx_t *ctx, dflow_key_t *key);
void dflow_state_gc(dflow_state_ctx_t *ctx, uint32_t timeout_sec);
void dflow_state_destroy(dflow_state_ctx_t *ctx);
uint32_t dflow_hash(dflow_key_t *key);

#endif // DFLOW_STATE_H
