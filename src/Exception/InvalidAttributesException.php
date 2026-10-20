<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Exception;

/**
 * Thrown when a "{ … }" attribute block cannot be parsed.
 *
 * The message names the offending text; a caller with line context (e.g.
 * {@see \AKlump\Directio\TextProcessor\ParseTasks}) may enrich it further.
 */
class InvalidAttributesException extends \InvalidArgumentException {

}
