#include <stdio.h>
#include <stdint.h>
#include <arpa/inet.h>
#include "../include/dflow.h"

/* 
 * CIDR & Routing Logic 
 */

int dflow_l3_is_in_subnet(uint32_t ip, uint32_t network, uint32_t mask) {
    return (ip & mask) == (network & mask);
}

void dflow_l3_parse_cidr(const char *cidr, uint32_t *network, uint32_t *mask) {
    char buf[MAX_IP_STR];
    strncpy(buf, cidr, MAX_IP_STR - 1);
    
    char *slash = strchr(buf, '/');
    if (slash) {
        *slash = '\0';
        int bits = atoi(slash + 1);
        *mask = htonl(0xFFFFFFFF << (32 - bits));
        inet_pton(AF_INET, buf, network);
        *network = ntohl(*network);
    }
}
