<?php

namespace Darory\WhatsApp\Exceptions;

/**
 * The instance is not running on the gateway (HTTP 404). Start it by connecting the number.
 */
class SessionNotFoundException extends DaroryWhatsAppException {}
