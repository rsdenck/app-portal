#include <stdio.h>
#include <string.h>
#include "../include/dflow.h"

/* Simple L7 Protocol Identification */

typedef struct {
    uint16_t port;
    const char *proto_name;
} l7_map_t;

static l7_map_t static_protos[] = {
    {80, "HTTP"},
    {443, "HTTPS"},
    {53, "DNS"},
    {22, "SSH"},
    {21, "FTP"},
    {25, "SMTP"},
    {161, "SNMP"},
    {0, NULL}
};

const char* dflow_l7_identify(uint16_t port) {
    for (int i = 0; static_protos[i].proto_name != NULL; i++) {
        if (static_protos[i].port == port) return static_protos[i].proto_name;
    }
    return "UNKNOWN";
}

void dflow_l7_deep_inspect(const unsigned char *payload, int len, dflow_record_t *rec) {
    /* 
     * In a full implementation, this would use libndpi 
     * Here we do basic string matching for SNI or common headers
     */
    if (len > 5 && memcmp(payload, "GET ", 4) == 0) {
        strncpy(rec->l7_proto, "HTTP", 31);
    } else if (len > 10 && payload[0] == 0x16 && payload[1] == 0x03) {
        strncpy(rec->l7_proto, "TLS/SSL", 31);
        // Logic to extract SNI from Client Hello would go here
    }
}
