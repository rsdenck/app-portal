#ifndef DFLOW_L7_H
#define DFLOW_L7_H

#include "dflow_state.h"

/**
 * Perform light DPI on packet payload
 */
void dflow_l7_inspect(dflow_session_t *session, const uint8_t *payload, uint32_t len);

/**
 * Extract TLS metadata (SNI, JA3) if applicable
 */
void dflow_l7_tls_extract(dflow_session_t *session, const uint8_t *payload, uint32_t len);

#endif // DFLOW_L7_H
