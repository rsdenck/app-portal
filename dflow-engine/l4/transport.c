#include <stdio.h>
#include <stdint.h>
#include "../include/dflow.h"

/* 
 * Transport Layer (L4) Analysis 
 */

void dflow_l4_parse_tcp(const unsigned char *payload, dflow_record_t *rec) {
    /* 
     * Extracts TCP Flags (SYN, ACK, FIN, RST)
     * Detects port knocking or half-open connections
     */
}

void dflow_l4_parse_udp(const unsigned char *payload, dflow_record_t *rec) {
    /* Extracts UDP metrics */
}
