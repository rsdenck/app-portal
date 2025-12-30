#include <stdio.h>
#include <math.h>
#include "../include/dflow.h"

/* 
 * Baseline Analysis Module 
 * Computes moving averages for traffic patterns per VLAN/IP.
 */

typedef struct {
    double avg_pps;
    double avg_bps;
    uint64_t samples;
} baseline_t;

void dflow_analytics_update_baseline(baseline_t *b, uint64_t current_pps, uint64_t current_bps) {
    b->samples++;
    b->avg_pps = (b->avg_pps * (b->samples - 1) + current_pps) / b->samples;
    b->avg_bps = (b->avg_bps * (b->samples - 1) + current_bps) / b->samples;
}
