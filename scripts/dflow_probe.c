/**
 * DFlow Probe - High Performance L2-L7 Network Traffic Analyzer
 * Language: C (Open Source)
 * Dependencies: libpcap, libndpi, libcurl
 * 
 * This probe performs real-time packet capture, DPI (Deep Packet Inspection),
 * and exports flow data + system metrics to the DFlow Portal.
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <pcap.h>
#include <time.h>
#include <curl/curl.h>

#define VERSION "2.1.0-enterprise-c"
#define CONFIG_FILE "/etc/dflow/probe.conf"

typedef struct {
    unsigned long packets;
    unsigned long bytes;
    double cpu_load;
    double mem_usage;
    char sensor_name[64];
    char portal_url[256];
} sensor_stats_t;

// Function to get system metrics (CPU/MEM)
void get_system_metrics(double *cpu, double *mem) {
    // Platform specific implementation (Linux /proc/stat)
    // Simplified for skeleton
    *cpu = 12.5; // Mock
    *mem = 45.2; // Mock
}

// Function to export metrics to Portal via JSON/HTTP
void export_metrics(sensor_stats_t *stats) {
    CURL *curl;
    CURLcode res;

    curl = curl_easy_init();
    if(curl) {
        char json_data[512];
        sprintf(json_data, 
            "{\"name\":\"%s\",\"version\":\"%s\",\"cpu\":%.2f,\"mem\":%.2f,\"pps\":%lu,\"bps\":%lu}",
            stats->sensor_name, VERSION, stats->cpu_load, stats->mem_usage, stats->packets, stats->bytes * 8);

        curl_easy_setopt(curl, CURLOPT_URL, stats->portal_url);
        curl_easy_setopt(curl, CURLOPT_POSTFIELDS, json_data);
        
        struct curl_slist *headers = NULL;
        headers = curl_slist_append(headers, "Content-Type: application/json");
        curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);

        res = curl_easy_perform(curl);
        if(res != CURLE_OK)
            fprintf(stderr, "export_metrics() failed: %s\n", curl_easy_strerror(res));

        curl_easy_cleanup(curl);
    }
}

int main(int argc, char *argv[]) {
    printf("DFlow Probe v%s starting...\n", VERSION);
    
    sensor_stats_t stats;
    strcpy(stats.sensor_name, "DFlow-Probe-C-01");
    strcpy(stats.portal_url, "http://localhost/portal/scripts/dflow_ingestor.php");
    
    // Main loop
    while(1) {
        get_system_metrics(&stats.cpu_load, &stats.mem_usage);
        
        // In a real scenario, these would be updated by the pcap callback
        stats.packets = 1500; 
        stats.bytes = 1048576; 

        printf("[HEARTBEAT] Exporting metrics to %s\n", stats.portal_url);
        export_metrics(&stats);
        
        sleep(10); // 10s interval
    }

    return 0;
}
