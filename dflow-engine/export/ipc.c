#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <errno.h>
#include <fcntl.h>
#include "../include/dflow.h"

static dflow_ipc_ctx_t g_ipc_ctx;

void dflow_export_ipc_init(const char *path) {
    memset(&g_ipc_ctx, 0, sizeof(dflow_ipc_ctx_t));
    strncpy(g_ipc_ctx.socket_path, path ? path : "/tmp/dflow_ingest.sock", sizeof(g_ipc_ctx.socket_path) - 1);
    g_ipc_ctx.fd = -1;
    g_ipc_ctx.connected = 0;
}

static int dflow_export_ipc_connect() {
    if (g_ipc_ctx.fd >= 0) close(g_ipc_ctx.fd);

    g_ipc_ctx.fd = socket(AF_UNIX, SOCK_STREAM, 0);
    if (g_ipc_ctx.fd < 0) return 0;

    int flags = fcntl(g_ipc_ctx.fd, F_GETFL, 0);
    fcntl(g_ipc_ctx.fd, F_SETFL, flags | O_NONBLOCK);

    struct sockaddr_un addr;
    memset(&addr, 0, sizeof(addr));
    addr.sun_family = AF_UNIX;
    strncpy(addr.sun_path, g_ipc_ctx.socket_path, sizeof(addr.sun_path) - 1);

    if (connect(g_ipc_ctx.fd, (struct sockaddr*)&addr, sizeof(addr)) < 0) {
        if (errno != EINPROGRESS) {
            close(g_ipc_ctx.fd);
            g_ipc_ctx.fd = -1;
            g_ipc_ctx.connected = 0;
            return 0;
        }
    }

    g_ipc_ctx.connected = 1;
    return 1;
}

void dflow_export_ipc_send(const char *data, int len) {
    if (!g_ipc_ctx.connected || g_ipc_ctx.fd < 0) {
        if (!dflow_export_ipc_connect()) return;
    }

    ssize_t sent = send(g_ipc_ctx.fd, data, len, MSG_NOSIGNAL);
    if (sent < 0) {
        if (errno != EAGAIN && errno != EWOULDBLOCK) {
            g_ipc_ctx.connected = 0;
            close(g_ipc_ctx.fd);
            g_ipc_ctx.fd = -1;
        }
    }
}

void dflow_export_ipc_close() {
    if (g_ipc_ctx.fd >= 0) {
        close(g_ipc_ctx.fd);
        g_ipc_ctx.fd = -1;
    }
    g_ipc_ctx.connected = 0;
}
