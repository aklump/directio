<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Exception;

/**
 * Thrown when a task's "{ … }" block is found inside another task's span.
 *
 * The message names both ids, outer first.
 */
class NestedTasksException extends \InvalidArgumentException {

}
