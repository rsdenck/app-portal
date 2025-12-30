#include <stdio.h>
#include "../include/dflow.h"

/* 
 * Anomaly Detection Module 
 * Detects spikes and patterns matching MITRE ATT&CK.
 */

void dflow_analytics_detect(uint64_t current, double avg, const char *type) {
    if (avg > 0 && current > avg * 3) {
        printf("[ANOMALY] High traffic spike detected: %lu (avg: %.2f) - Type: %s\n", 
               current, avg, type);
        
        /* 
         * MITRE Mapping:
         * T1046: Network Service Scanning
         * T1567: Exfiltration Over Web Service
         */
    }
}
