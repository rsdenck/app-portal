CC = gcc
CFLAGS = -Wall -O3 -Iinclude
LDFLAGS = -lpcap -lpthread -lm

SRCS = core/engine.c \
       core/config.c \
       core/time.c \
       core/snmp.c \
       ingest/packet_ingest.c \
       ingest/flow_ingest.c \
       l2/vlan.c \
       l2/mac.c \
       l2/topology.c \
       l3/ip.c \
       l3/cidr.c \
       l3/routing.c \
       l4/transport.c \
       l4/ports.c \
       l4/services.c \
       l4/vuln.c \
       l7/dpi.c \
       active/controller.c \
       active/scheduler.c \
       active/targets.c \
       active/probes/tcp_syn.c \
       active/probes/tcp_banner.c \
       active/probes/tls_fp.c \
       active/probes/http_fp.c \
       active/probes/smb_fp.c \
       active/results/merge.c \
       active/results/confidence.c \
       analytics/baseline.c \
       analytics/anomaly.c \
       analytics/score.c \
       analytics/timeline.c \
       export/ipc.c \
       export/netflow.c \
       export/json.c \
       storage/writer.c

OBJS = $(SRCS:.c=.o)
TARGET = dflow-engine-bin

all: $(TARGET)

$(TARGET): $(OBJS)
	$(CC) $(OBJS) -o $(TARGET) $(LDFLAGS)

%.o: %.c
	$(CC) $(CFLAGS) -c $< -o $@

clean:
	rm -f $(OBJS) $(TARGET)
