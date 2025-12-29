#include <string.h>
#include <ctype.h>
#include <stdio.h>
#include "dflow_l7.h"

// Helper to find a pattern in payload
static const uint8_t* find_pattern(const uint8_t *payload, uint32_t len, const uint8_t *pattern, uint32_t pat_len) {
    if (len < pat_len) return NULL;
    for (uint32_t i = 0; i <= len - pat_len; i++) {
        if (memcmp(payload + i, pattern, pat_len) == 0) return payload + i;
    }
    return NULL;
}

// Minimal MD5 implementation for JA3
typedef struct {
    uint32_t state[4];
    uint32_t count[2];
    uint8_t buffer[64];
} md5_ctx;

#define S11 7
#define S12 12
#define S13 17
#define S14 22
#define S21 5
#define S22 9
#define S23 14
#define S24 20
#define S31 4
#define S32 11
#define S33 16
#define S34 23
#define S41 6
#define S42 10
#define S43 15
#define S44 21

static void md5_transform(uint32_t state[4], const uint8_t block[64]);
static void md5_init(md5_ctx *context);
static void md5_update(md5_ctx *context, const uint8_t *input, uint32_t inputLen);
static void md5_final(uint8_t digest[16], md5_ctx *context);

#define F(x, y, z) (((x) & (y)) | ((~x) & (z)))
#define G(x, y, z) (((x) & (z)) | ((y) & (~z)))
#define H(x, y, z) ((x) ^ (y) ^ (z))
#define I(x, y, z) ((y) ^ ((x) | (~z)))
#define ROTATE_LEFT(x, n) (((x) << (n)) | ((x) >> (32-(n))))

#define FF(a, b, c, d, x, s, ac) { \
 (a) += F ((b), (c), (d)) + (x) + (uint32_t)(ac); \
 (a) = ROTATE_LEFT ((a), (s)); \
 (a) += (b); \
 }
#define GG(a, b, c, d, x, s, ac) { \
 (a) += G ((b), (c), (d)) + (x) + (uint32_t)(ac); \
 (a) = ROTATE_LEFT ((a), (s)); \
 (a) += (b); \
 }
#define HH(a, b, c, d, x, s, ac) { \
 (a) += H ((b), (c), (d)) + (x) + (uint32_t)(ac); \
 (a) = ROTATE_LEFT ((a), (s)); \
 (a) += (b); \
 }
#define II(a, b, c, d, x, s, ac) { \
 (a) += I ((b), (c), (d)) + (x) + (uint32_t)(ac); \
 (a) = ROTATE_LEFT ((a), (s)); \
 (a) += (b); \
 }

static void md5_init(md5_ctx *context) {
    context->count[0] = context->count[1] = 0;
    context->state[0] = 0x67452301;
    context->state[1] = 0xefcdab89;
    context->state[2] = 0x98badcfe;
    context->state[3] = 0x10325476;
}

static void md5_update(md5_ctx *context, const uint8_t *input, uint32_t inputLen) {
    uint32_t i, index, partLen;
    index = (uint32_t)((context->count[0] >> 3) & 0x3F);
    if ((context->count[0] += ((uint32_t)inputLen << 3)) < ((uint32_t)inputLen << 3))
        context->count[1]++;
    context->count[1] += ((uint32_t)inputLen >> 29);
    partLen = 64 - index;
    if (inputLen >= partLen) {
        memcpy(&context->buffer[index], input, partLen);
        md5_transform(context->state, context->buffer);
        for (i = partLen; i + 63 < inputLen; i += 64)
            md5_transform(context->state, &input[i]);
        index = 0;
    } else i = 0;
    memcpy(&context->buffer[index], &input[i], inputLen - i);
}

static void md5_final(uint8_t digest[16], md5_ctx *context) {
    uint8_t bits[8];
    uint32_t index, padLen;
    static uint8_t PADDING[64] = { 0x80, 0 };
    memcpy(bits, context->count, 8);
    index = (uint32_t)((context->count[0] >> 3) & 0x3f);
    padLen = (index < 56) ? (56 - index) : (120 - index);
    md5_update(context, PADDING, padLen);
    md5_update(context, bits, 8);
    memcpy(digest, context->state, 16);
}

static void md5_transform(uint32_t state[4], const uint8_t block[64]) {
    uint32_t a = state[0], b = state[1], c = state[2], d = state[3], x[16];
    for (int i = 0, j = 0; i < 16; i++, j += 4)
        x[i] = ((uint32_t)block[j]) | (((uint32_t)block[j+1]) << 8) | (((uint32_t)block[j+2]) << 16) | (((uint32_t)block[j+3]) << 24);

    FF (a, b, c, d, x[ 0], S11, 0xd76aa478); FF (d, a, b, c, x[ 1], S12, 0xe8c7b756); FF (c, d, a, b, x[ 2], S13, 0x242070db); FF (b, c, d, a, x[ 3], S14, 0xc1bdceee);
    FF (a, b, c, d, x[ 4], S11, 0xf57c0faf); FF (d, a, b, c, x[ 5], S12, 0x4787c62a); FF (c, d, a, b, x[ 6], S13, 0xa8304613); FF (b, c, d, a, x[ 7], S14, 0xfd469501);
    FF (a, b, c, d, x[ 8], S11, 0x698098d8); FF (d, a, b, c, x[ 9], S12, 0x8b44f7af); FF (c, d, a, b, x[10], S13, 0xffff5bb1); FF (b, c, d, a, x[11], S14, 0x895cd7be);
    FF (a, b, c, d, x[12], S11, 0x6b901122); FF (d, a, b, c, x[13], S12, 0xfd987193); FF (c, d, a, b, x[14], S13, 0xa679438e); FF (b, c, d, a, x[15], S14, 0x49b40821);
    GG (a, b, c, d, x[ 1], S21, 0xf61e2562); GG (d, a, b, c, x[ 6], S22, 0xc040b340); GG (c, d, a, b, x[11], S23, 0x265e5a51); GG (b, c, d, a, x[ 0], S24, 0xe9b6c7aa);
    GG (a, b, c, d, x[ 5], S21, 0xd62f105d); GG (d, a, b, c, x[10], S22,  0x2441453); GG (c, d, a, b, x[15], S23, 0xd8a1e681); GG (b, c, d, a, x[ 4], S24, 0xe7d3fbc8);
    GG (a, b, c, d, x[ 9], S21, 0x21e1cde6); GG (d, a, b, c, x[14], S22, 0xc33707d6); GG (c, d, a, b, x[ 3], S23, 0xf4d50d87); GG (b, c, d, a, x[ 8], S24, 0x455a14ed);
    GG (a, b, c, d, x[13], S21, 0xa9e3e905); GG (d, a, b, c, x[ 2], S22, 0xfcefa3f8); GG (c, d, a, b, x[ 7], S23, 0x676f02d9); GG (b, c, d, a, x[12], S24, 0x8d2a4c8a);
    HH (a, b, c, d, x[ 5], S31, 0xff447381); HH (d, a, b, c, x[ 8], S32, 0x676f02d9); HH (c, d, a, b, x[11], S33, 0x8d2a4c8a); HH (b, c, d, a, x[14], S34, 0xff447381);
    HH (a, b, c, d, x[ 1], S31, 0x6d9d6122); HH (d, a, b, c, x[ 4], S32, 0xfde5380c); HH (c, d, a, b, x[ 7], S33, 0xa4beea44); HH (b, c, d, a, x[10], S34, 0x4bdecfa9);
    HH (a, b, c, d, x[13], S31, 0xf6bb4b60); HH (d, a, b, c, x[ 0], S32, 0xbebfbc70); HH (c, d, a, b, x[ 3], S33, 0x289b7ec6); HH (b, c, d, a, x[ 6], S34, 0xeaa127fa);
    HH (a, b, c, d, x[ 9], S31, 0xd4ef3085); HH (d, a, b, c, x[12], S32,  0x4881d05); HH (c, d, a, b, x[15], S33, 0xd9d4d039); HH (b, c, d, a, x[ 2], S34, 0xe6db99e5);
    II (a, b, c, d, x[ 0], S41, 0xf4292244); II (d, a, b, c, x[ 7], S42, 0x432aff97); II (c, d, a, b, x[14], S43, 0xab9423a7); II (b, c, d, a, x[ 5], S44, 0xfc93a039);
    II (a, b, c, d, x[12], S41, 0x655b59c3); II (d, a, b, c, x[ 3], S42, 0x8f0ccc92); II (c, d, a, b, x[10], S43, 0xffeff47d); II (b, c, d, a, x[ 1], S44, 0x85845dd1);
    II (a, b, c, d, x[ 8], S41, 0x6fa87e4f); II (d, a, b, c, x[15], S42, 0xfe2ce6e0); II (c, d, a, b, x[ 6], S43, 0xa3014314); II (b, c, d, a, x[13], S44, 0x4e0811a1);
    II (a, b, c, d, x[ 4], S41, 0xf7537e82); II (d, a, b, c, x[11], S42, 0xbd3af235); II (c, d, a, b, x[ 2], S43, 0x2ad7d2bb); II (b, c, d, a, x[ 9], S44, 0xeb86d391);
    state[0] += a; state[1] += b; state[2] += c; state[3] += d;
}

void dflow_l7_inspect(dflow_session_t *session, const uint8_t *payload, uint32_t len) {
    if (session->l7_proto[0] != '\0' && strcmp(session->l7_proto, "TLS") != 0) return;

    // L7 Protocol Detection
    if (len > 4) {
        // HTTP
        if (memcmp(payload, "GET ", 4) == 0 || memcmp(payload, "POST", 4) == 0 || 
            memcmp(payload, "HTTP/", 5) == 0 || memcmp(payload, "PUT ", 4) == 0) {
            strcpy(session->l7_proto, "HTTP");
        } 
        // TLS / SSL
        else if (payload[0] == 0x16 && payload[1] == 0x03) {
            strcpy(session->l7_proto, "TLS");
            dflow_l7_tls_extract(session, payload, len);
        }
        // DNS
        else if (session->key.dst_port == 53 || session->key.src_port == 53) {
            strcpy(session->l7_proto, "DNS");
        }
        // SSH
        else if (memcmp(payload, "SSH-", 4) == 0) {
            strcpy(session->l7_proto, "SSH");
        }
        // Bittorrent
        else if (payload[0] == 0x13 && memcmp(payload + 1, "BitTorrent protocol", 19) == 0) {
            strcpy(session->l7_proto, "BitTorrent");
        }
        // QUIC (Simple check for initial packet)
        else if ((payload[0] & 0x80) && (payload[0] & 0x40)) {
            strcpy(session->l7_proto, "QUIC");
        }
    }

    // Fallback to port-based if still empty after some packets
    if (session->l7_proto[0] == '\0' && session->pkts_in + session->pkts_out > 5) {
        if (session->key.dst_port == 80 || session->key.src_port == 80) strcpy(session->l7_proto, "HTTP");
        else if (session->key.dst_port == 443 || session->key.src_port == 443) strcpy(session->l7_proto, "HTTPS/TLS");
        else if (session->key.dst_port == 22 || session->key.src_port == 22) strcpy(session->l7_proto, "SSH");
        else if (session->key.dst_port == 21 || session->key.src_port == 21) strcpy(session->l7_proto, "FTP");
        else if (session->key.dst_port == 25 || session->key.src_port == 25) strcpy(session->l7_proto, "SMTP");
        else if (session->key.dst_port == 161 || session->key.src_port == 161) strcpy(session->l7_proto, "SNMP");
    }
}

void dflow_l7_tls_extract(dflow_session_t *session, const uint8_t *payload, uint32_t len) {
    if (len < 43) return; 

    // Check if it's a Client Hello (0x01)
    if (payload[0] != 0x16 || payload[5] != 0x01) return;

    uint16_t tls_ver = (payload[9] << 8) | payload[10];
    
    // JA3 Components
    char ciphers_str[512] = "";
    char exts_str[256] = "";
    char groups_str[256] = "";
    char formats_str[64] = "";

    // Skip Handshake header (4 bytes), Version (2 bytes), Random (32 bytes)
    // Session ID length (1 byte)
    uint8_t sess_id_len = payload[43];
    uint32_t offset = 44 + sess_id_len;

    // Cipher Suites
    if (offset + 2 > len) return;
    uint16_t cipher_len = (payload[offset] << 8) | payload[offset+1];
    offset += 2;
    for (uint16_t i = 0; i < cipher_len && i < 200; i += 2) {
        if (offset + i + 2 > len) break;
        uint16_t cipher = (payload[offset+i] << 8) | payload[offset+i+1];
        // Skip GREASE ciphers
        if ((cipher & 0x0F0F) == 0x0A0A) continue;
        char tmp[8];
        sprintf(tmp, "%d-", cipher);
        strcat(ciphers_str, tmp);
    }
    if (strlen(ciphers_str) > 0) ciphers_str[strlen(ciphers_str)-1] = '\0';
    offset += cipher_len;

    // Compression Methods
    if (offset + 1 > len) return;
    uint8_t comp_len = payload[offset];
    offset += 1 + comp_len;

    // Extensions
    if (offset + 2 > len) return;
    uint16_t extensions_len = (payload[offset] << 8) | payload[offset+1];
    offset += 2;

    uint32_t ext_end = offset + extensions_len;
    if (ext_end > len) ext_end = len;

    while (offset + 4 <= ext_end) {
        uint16_t ext_type = (payload[offset] << 8) | payload[offset+1];
        uint16_t ext_len = (payload[offset+2] << 8) | payload[offset+3];
        offset += 4;

        // Skip GREASE extensions
        if ((ext_type & 0x0F0F) != 0x0A0A) {
            char tmp[8];
            sprintf(tmp, "%d-", ext_type);
            strcat(exts_str, tmp);
        }

        if (ext_type == 0x0000) { // SNI
            if (offset + ext_len <= ext_end && ext_len > 5) {
                uint16_t name_len = (payload[offset+4] << 8) | payload[offset+5];
                if (offset + 6 + name_len <= ext_end) {
                    if (name_len >= sizeof(session->sni)) name_len = sizeof(session->sni) - 1;
                    memcpy(session->sni, &payload[offset+6], name_len);
                    session->sni[name_len] = '\0';
                }
            }
        } else if (ext_type == 0x000a) { // Supported Groups
            uint16_t groups_len = (payload[offset] << 8) | payload[offset+1];
            for (uint16_t i = 0; i < groups_len; i += 2) {
                if (offset + 2 + i + 2 > ext_end) break;
                uint16_t group = (payload[offset+2+i] << 8) | payload[offset+2+i+1];
                if ((group & 0x0F0F) == 0x0A0A) continue;
                char tmp[8];
                sprintf(tmp, "%d-", group);
                strcat(groups_str, tmp);
            }
            if (strlen(groups_str) > 0) groups_str[strlen(groups_str)-1] = '\0';
        } else if (ext_type == 0x000b) { // EC Point Formats
            uint8_t formats_len = payload[offset];
            for (uint8_t i = 0; i < formats_len; i++) {
                if (offset + 1 + i + 1 > ext_end) break;
                char tmp[8];
                sprintf(tmp, "%d-", payload[offset+1+i]);
                strcat(formats_str, tmp);
            }
            if (strlen(formats_str) > 0) formats_str[strlen(formats_str)-1] = '\0';
        }
        offset += ext_len;
    }
    if (strlen(exts_str) > 0) exts_str[strlen(exts_str)-1] = '\0';

    // Construct JA3 String: Version,Ciphers,Extensions,Groups,Formats
    char full_ja3[1024];
    snprintf(full_ja3, sizeof(full_ja3), "%d,%s,%s,%s,%s", 
             tls_ver, ciphers_str, exts_str, groups_str, formats_str);

    // MD5 Hash of JA3 string
    md5_ctx ctx;
    uint8_t digest[16];
    md5_init(&ctx);
    md5_update(&ctx, (uint8_t*)full_ja3, strlen(full_ja3));
    md5_final(digest, &ctx);

    for (int i = 0; i < 16; i++) {
        sprintf(&session->ja3[i*2], "%02x", digest[i]);
    }
    session->ja3[32] = '\0';
}
