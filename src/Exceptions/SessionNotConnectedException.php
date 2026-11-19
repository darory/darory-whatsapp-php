<?php

namespace Darory\WhatsApp\Exceptions;

/**
 * The instance exists but its number is not connected yet. Scan the QR code or use a pairing code.
 */
class SessionNotConnectedException extends DaroryWhatsAppException {}
