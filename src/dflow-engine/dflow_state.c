#include <stdlib.h>
#include <string.h>
#include "dflow_state.h"

/**
 * Jenkins Hash for 5-tuple (IPv4 & IPv6 compatible)
 */
uint32_t dflow_hash(dflow_key_t *key) {
    uint32_t hash = 0;
    
    if (key->is_ipv6) {
        for (int i = 0; i < 16; i++) {
            hash += key->src_ip.v6[i];
            hash += (hash << 10); hash ^= (hash >> 6);
            hash += key->dst_ip.v6[i];
            hash += (hash << 10); hash ^= (hash >> 6);
        }
    } else {
        hash += key->src_ip.v4;
        hash += (hash << 10); hash ^= (hash >> 6);
        hash += key->dst_ip.v4;
        hash += (hash << 10); hash ^= (hash >> 6);
    }
    
    hash += key->src_port;
    hash += (hash << 10); hash ^= (hash >> 6);
    hash += key->dst_port;
    hash += (hash << 10); hash ^= (hash >> 6);
    hash += key->protocol;
    hash += (hash << 10); hash ^= (hash >> 6);
    
    hash += (hash << 3);
    hash ^= (hash >> 11);
    hash += (hash << 15);
    
    return hash % HASH_SIZE;
}

dflow_state_ctx_t* dflow_state_init(int thread_id, int cpu_core) {
    dflow_state_ctx_t *ctx = calloc(1, sizeof(dflow_state_ctx_t));
    ctx->thread_id = thread_id;
    ctx->cpu_core = cpu_core;
    return ctx;
}

dflow_session_t* dflow_state_get_or_create(dflow_state_ctx_t *ctx, dflow_key_t *key) {
    uint32_t h = dflow_hash(key);
    dflow_session_t *s = ctx->table[h];

    // Search existing
    while (s) {
        if (s->key.is_ipv6 == key->is_ipv6 &&
            s->key.src_port == key->src_port &&
            s->key.dst_port == key->dst_port &&
            s->key.protocol == key->protocol) {
            
            if (key->is_ipv6) {
                if (memcmp(s->key.src_ip.v6, key->src_ip.v6, 16) == 0 &&
                    memcmp(s->key.dst_ip.v6, key->dst_ip.v6, 16) == 0) {
                    return s;
                }
            } else {
                if (s->key.src_ip.v4 == key->src_ip.v4 &&
                    s->key.dst_ip.v4 == key->dst_ip.v4) {
                    return s;
                }
            }
        }
        ctx->metrics.hash_collisions++;
        s = s->next;
    }

    // Create new
    s = calloc(1, sizeof(dflow_session_t));
    memcpy(&s->key, key, sizeof(dflow_key_t));
    s->first_seen = time(NULL);
    s->last_seen = s->first_seen;
    
    // Insert at head
    s->next = ctx->table[h];
    ctx->table[h] = s;
    
    ctx->metrics.active_sessions++;
    ctx->metrics.total_flows++;
    
    return s;
}

void dflow_state_gc(dflow_state_ctx_t *ctx, uint32_t timeout_sec) {
    time_t now = time(NULL);
    for (int i = 0; i < HASH_SIZE; i++) {
        dflow_session_t **curr = &ctx->table[i];
        while (*curr) {
            dflow_session_t *s = *curr;
            if (now - s->last_seen > timeout_sec) {
                // Expired
                *curr = s->next;
                free(s);
                ctx->metrics.active_sessions--;
            } else {
                curr = &s->next;
            }
        }
    }
}

void dflow_state_destroy(dflow_state_ctx_t *ctx) {
    if (!ctx) return;
    for (int i = 0; i < HASH_SIZE; i++) {
        dflow_session_t *s = ctx->table[i];
        while (s) {
            dflow_session_t *tmp = s;
            s = s->next;
            free(tmp);
        }
    }
    free(ctx);
}
