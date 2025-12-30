#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <arpa/inet.h>
#include "../include/dflow.h"

/* IP to MAC/VLAN Correlation Table */
#define IP_HASH_SIZE 8192

typedef struct ip_entry {
    uint32_t ip;
    char mac[MAX_MAC_STR];
    uint16_t vlan_id;
    uint64_t bytes_sent;
    uint64_t bytes_recv;
    struct ip_entry *next;
} ip_entry_t;

static ip_entry_t *ip_table[IP_HASH_SIZE];

unsigned int ip_hash(uint32_t ip) {
    return ip % IP_HASH_SIZE;
}

void dflow_l3_update_ip(uint32_t ip, const char *mac, uint16_t vlan_id) {
    unsigned int h = ip_hash(ip);
    ip_entry_t *entry = ip_table[h];
    
    while (entry) {
        if (entry->ip == ip) {
            if (mac) strncpy(entry->mac, mac, MAX_MAC_STR - 1);
            entry->vlan_id = vlan_id;
            return;
        }
        entry = entry->next;
    }
    
    entry = malloc(sizeof(ip_entry_t));
    entry->ip = ip;
    if (mac) strncpy(entry->mac, mac, MAX_MAC_STR - 1);
    else entry->mac[0] = '\0';
    entry->vlan_id = vlan_id;
    entry->bytes_sent = 0;
    entry->bytes_recv = 0;
    entry->next = ip_table[h];
    ip_table[h] = entry;
}

void dflow_l3_add_traffic(uint32_t ip, uint64_t bytes, int is_sent) {
    unsigned int h = ip_hash(ip);
    ip_entry_t *entry = ip_table[h];
    
    while (entry) {
        if (entry->ip == ip) {
            if (is_sent) entry->bytes_sent += bytes;
            else entry->bytes_recv += bytes;
            return;
        }
        entry = entry->next;
    }
}
