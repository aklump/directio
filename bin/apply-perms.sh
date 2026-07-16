#!/usr/bin/env bash

# @file
# Creates a configured controller for aklump/easy-perms.

set -euo pipefail

s="${BASH_SOURCE[0]}";[[ "$s" ]] || s="${(%):-%N}";while [ -h "$s" ];do d="$(cd -P "$(dirname "$s")" && pwd)";s="$(readlink "$s")";[[ $s != /* ]] && s="$d/$s";done;__DIR__=$(cd -P "$(dirname "$s")" && pwd)

# ========= Configuration =========
main_config="$__DIR__/config/easy-perms.yml"
dev_config="$__DIR__/config/easy-perms.dev.yml"

function is_prod() {
  # Modify this to return 0 when called from the production environment.
  [[ "${USER:-$(id -un)}" == "foobar" ]]
}
# ========= End configuration =========

base="$__DIR__/../"

if [[ -d "$base/.easy-perms" ]]; then
  base="$base/.easy-perms"
fi

base="$(cd "$base" && pwd)"
easy_perms="$base/vendor/bin/easy-perms"

if [[ ! -f "$easy_perms" ]]; then
  echo "Missing executable: $easy_perms" >&2
  exit 1
fi

if [[ ! -x "$easy_perms" ]]; then
  chmod u+x "$easy_perms"
fi

if [[ ! -f "$main_config" ]]; then
  echo "Missing configuration file: $main_config" >&2
  exit 1
fi

config_paths=("$main_config")
if ! is_prod && [[ -f "$dev_config" ]]; then
  config_paths+=("$dev_config")
fi

"$easy_perms" apply "${config_paths[@]}" "$@"
