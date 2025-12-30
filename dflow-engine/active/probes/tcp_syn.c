#include <stdio.h>
#include <stdint.h>
#include "../include/dflow.h"

/* 
 * TCP SYN Probe - Stealthy Port Scanning 
 * Uses raw sockets for high-performance scanning.
 */

void dflow_active_probe_tcp_syn(uint32_t ip, uint16_t port) {
    /* 
     * In a full implementation, this creates a raw socket:
     * int s = socket(AF_INET, SOCK_RAW, IPPROTO_TCP);
     * 
     * It then crafts a TCP SYN packet and sends it without waiting 
     * for a full handshake. Responses are captured by the main ingest loop.
     */
    printf("[ACTIVE] SYN Probe -> %u.%u.%u.%u:%u\n", 
           (ip >> 24) & 0xFF, (ip >> 16) & 0xFF, (ip >> 8) & 0xFF, ip & 0xFF, port);
}
