<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Model;

interface TaskStateInterface {

  public function getCompleted(): string;

  public function getId(): string;

}
