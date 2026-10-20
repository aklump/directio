<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Exception;

/**
 * Thrown when a document still contains the retired "<directio" element
 * markup. Run `directio convert` on the file to move it onto the heading
 * syntax.
 */
class LegacySyntaxException extends \InvalidArgumentException {

}
