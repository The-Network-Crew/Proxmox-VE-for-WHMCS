<?php /* vim: set ts=2 sw=2 tw=0 et :*/

class Ipv4_Mac
{
  private $mac;
  const ERROR_ADDR_FORMAT = 'MAC address string format error';

  /**
   * fromString
   * Creates Ipv4_Mac object from a MAC address string in various formats
   *
   * @param string $data
   * @static
   * @access public
   * @return Ipv4_Mac
   */
  static function fromString($data) {
    $normalized = self::normalizeMac($data);
    return new self($normalized);
  }

  /**
   * normalizeMac
   * Converts supported MAC address formats to uppercase colon-delimited string
   *
   * @param string $data
   * @static
   * @access private
   * @return string
   */
  private static function normalizeMac($data) {
    if (!is_string($data)) {
      throw new Exception(self::ERROR_ADDR_FORMAT);
    }

    $candidate = trim($data);
    if ($candidate === '') {
      throw new Exception(self::ERROR_ADDR_FORMAT);
    }

    // Drop hexadecimal prefixes (e.g. 0xAABBCCDDEEFF)
    if (stripos($candidate, '0x') === 0) {
      $candidate = substr($candidate, 2);
    }

    // Remove standard separators (: - .)
    $candidate = str_replace(array(':', '-', '.'), '', $candidate);

    // Cisco-style dotted format (AABB.CCDD.EEFF) leaves dots removed but keeps full length,
    // so ensure we only have hexadecimal characters remaining.
    if (!preg_match('/^[a-fA-F0-9]{12}$/', $candidate)) {
      throw new Exception(self::ERROR_ADDR_FORMAT);
    }

    $candidate = strtoupper($candidate);
    $chunks = str_split($candidate, 2);

    return implode(':', $chunks);
  }

  /**
   * toString
   * Returns normalized MAC address
   *
   * @access public
   * @return string
   */
  public function toString() {
    return $this->mac;
  }

  /**
   * __toString
   * Magic method returns normalized MAC address
   *
   * @access public
   * @return string
   */
  public function __toString() {
    return $this->toString();
  }

  /**
   * __construct
   * Private constructor
   *
   * @param string $mac
   * @access private
   * @return void
   */
  private function __construct($mac) {
    $this->mac = $mac;
  }
}
