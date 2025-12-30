#include <stdio.h>
#include <stdint.h>
#include "../include/dflow.h"

/* 
 * L4 Port Analysis & State Engine
 */

#define PORT_HASH_SIZE 1024

typedef struct port_entry {
    uint16_t port;
    uint32_t connection_count;
    uint64_t total_bytes;
    uint8_t is_suspicious;
    struct port_entry *next;
} port_entry_t;

static port_entry_t *port_table[PORT_HASH_SIZE];

void dflow_l4_port_analysis(uint16_t port) {
    uint16_t h = port % PORT_HASH_SIZE;
    port_entry_t *entry = port_table[h];

    while (entry) {
        if (entry->port == port) {
            entry->connection_count++;
            return;
        }
        entry = entry->next;
    }

    /* New port usage tracked */
    entry = malloc(sizeof(port_entry_t));
    entry->port = port;
    entry->connection_count = 1;
    entry->total_bytes = 0;
    entry->is_suspicious = (port > 1024 && port < 5000) ? 0 : 0; // Placeholder logic
    entry->next = port_table[h];
    port_table[h] = entry;
}
