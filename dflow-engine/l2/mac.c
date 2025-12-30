#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include "../include/dflow.h"

/* Simple Hash Table for MAC to VLAN correlation */
#define MAC_HASH_SIZE 4096

typedef struct mac_entry {
    char mac[MAX_MAC_STR];
    uint16_t vlan_id;
    uint32_t last_seen;
    struct mac_entry *next;
} mac_entry_t;

static mac_entry_t *mac_table[MAC_HASH_SIZE];

unsigned int mac_hash(const char *mac) {
    unsigned int hash = 0;
    while (*mac) hash = (hash << 5) + *mac++;
    return hash % MAC_HASH_SIZE;
}

void dflow_l2_update_mac(const char *mac, uint16_t vlan_id) {
    unsigned int h = mac_hash(mac);
    mac_entry_t *entry = mac_table[h];
    
    while (entry) {
        if (strcmp(entry->mac, mac) == 0) {
            entry->vlan_id = vlan_id;
            entry->last_seen = (uint32_t)time(NULL);
            return;
        }
        entry = entry->next;
    }
    
    /* New entry */
    entry = malloc(sizeof(mac_entry_t));
    strncpy(entry->mac, mac, MAX_MAC_STR - 1);
    entry->vlan_id = vlan_id;
    entry->last_seen = (uint32_t)time(NULL);
    entry->next = mac_table[h];
    mac_table[h] = entry;
    
    printf("[L2] New MAC discovered: %s on VLAN %u\n", mac, vlan_id);
}

uint16_t dflow_l2_get_vlan(const char *mac) {
    unsigned int h = mac_hash(mac);
    mac_entry_t *entry = mac_table[h];
    
    while (entry) {
        if (strcmp(entry->mac, mac) == 0) return entry->vlan_id;
        entry = entry->next;
    }
    return 0;
}
