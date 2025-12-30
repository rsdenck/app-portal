#include <stdio.h>
#include <stdint.h>
#include "../include/dflow.h"

/* 
 * VLAN Analysis Engine
 * Tracks VLAN usage, density, and health.
 */

typedef struct vlan_entry {
    uint16_t vlan_id;
    uint32_t active_hosts;
    uint64_t total_bytes;
    uint64_t total_packets;
    uint32_t last_active;
    struct vlan_entry *next;
} vlan_entry_t;

static vlan_entry_t *vlan_list = NULL;

void dflow_l2_vlan_process(uint16_t vlan_id) {
    vlan_entry_t *curr = vlan_list;
    while (curr) {
        if (curr->vlan_id == vlan_id) {
            curr->last_active = (uint32_t)time(NULL);
            return;
        }
        curr = curr->next;
    }

    /* New VLAN discovered */
    vlan_entry_t *new_vlan = malloc(sizeof(vlan_entry_t));
    new_vlan->vlan_id = vlan_id;
    new_vlan->active_hosts = 0;
    new_vlan->total_bytes = 0;
    new_vlan->total_packets = 0;
    new_vlan->last_active = (uint32_t)time(NULL);
    new_vlan->next = vlan_list;
    vlan_list = new_vlan;

    printf("[L2] New VLAN discovered in flow: %u\n", vlan_id);
}

void dflow_l2_vlan_stats_update(uint16_t vlan_id, uint64_t bytes, uint64_t pkts) {
    vlan_entry_t *curr = vlan_list;
    while (curr) {
        if (curr->vlan_id == vlan_id) {
            curr->total_bytes += bytes;
            curr->total_packets += pkts;
            return;
        }
        curr = curr->next;
    }
}
