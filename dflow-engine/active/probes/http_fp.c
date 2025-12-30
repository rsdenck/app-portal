#include <stdio.h>
#include <stdint.h>
#include "../include/dflow.h"

/* 
 * HTTP Fingerprinting Probe
 */

void dflow_active_probe_http_fp(uint32_t ip, uint16_t port) {
    printf("[ACTIVE] HTTP Fingerprint -> %u.%u.%u.%u:%u\n", 
           (ip >> 24) & 0xFF, (ip >> 16) & 0xFF, (ip >> 8) & 0xFF, ip & 0xFF, port);
    
    /* 
     * Logic:
     * 1. Establish full TCP connection
     * 2. Send "GET / HTTP/1.1\r\n\r\n"
     * 3. Analyze "Server:" header and response body
     */
}
