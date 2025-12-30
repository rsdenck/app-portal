#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <signal.h>
#include <unistd.h>
#include "dflow.h"

static dflow_engine_ctx_t engine_ctx;

void handle_signal(int sig) {
    printf("\n[CORE] Shutdown signal received (%d). Cleaning up...\n", sig);
    engine_ctx.running = 0;
}

void dflow_core_init(dflow_engine_ctx_t *ctx) {
    memset(ctx, 0, sizeof(dflow_engine_ctx_t));
    ctx->running = 1;
    strncpy(ctx->sensor_name, "DFlow-Native-Core-01", 127);
    printf("[CORE] Engine %s initialized.\n", DFLOW_VERSION);
}

int main(int argc, char *argv[]) {
    signal(SIGINT, handle_signal);
    signal(SIGTERM, handle_signal);

    dflow_core_init(&engine_ctx);
    dflow_config_load("engine.conf");
    dflow_snmp_init();
    dflow_export_ipc_init(NULL);

    printf("[CORE] Starting DFlow Native Engine %s...\n", DFLOW_VERSION);
    printf("[CORE] Sensor: %s\n", engine_ctx.sensor_name);

    /* Start Capture Thread */
    dflow_ingest_start("eth0");

    while(engine_ctx.running) {
        // Main loop for maintenance and stats reporting
        printf("[STATS] Pkts: %lu | Bytes: %lu | Active Flows: %u\n", 
               engine_ctx.total_packets, engine_ctx.total_bytes, engine_ctx.active_flows);
        
        // Active probing scheduler tick
        dflow_active_scheduler_tick();
        
        // Update export data for Frontend
        dflow_storage_write_json("dflow_stats.json", &engine_ctx);
        
        // Periodic analytics and maintenance
        if (time(NULL) % 60 == 0) {
            dflow_l2_topology_compute();
            dflow_snmp_poll_all();
            dflow_analytics_score_calc();
        }

        sleep(5);
    }

    printf("[CORE] Engine stopped gracefully.\n");
    return 0;
}
