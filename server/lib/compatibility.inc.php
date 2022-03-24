<?php

/*
Copyright (c) 2021, Jesse Norell <jesse@kci.net>
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/* random_bytes can be dropped when php 5.6 support is dropped */
if (! function_exists('random_bytes')) {
	function random_bytes($length) {
		return openssl_random_pseudo_bytes($length);
	}
}

/* random_int can be dropped when php 5.6 support is dropped */
if (! function_exists('random_int')) {
	function random_int($min=null, $max=null) {
		if (null === $min) {
			$min = PHP_INT_MIN;
		}

		if (null === $max) {
			$min = PHP_INT_MAX;
		}

		if (!is_int($min) || !is_int($max)) {
			trigger_error('random_int: $min and $max must be integer values', E_USER_NOTICE);
			$min = (int)$min;
			$max = (int)$max;
		}

		if ($min > $max) {
			trigger_error('random_int: $max can\'t be lesser than $min', E_USER_WARNING);
			return null;
		}

		$range = $counter = $max - $min;
		$bits = 1;

		while ($counter >>= 1) {
			++$bits;
		}

		$bytes = (int)max(ceil($bits/8), 1);
		$bitmask = pow(2, $bits) - 1;

		if ($bitmask >= PHP_INT_MAX) {
			$bitmask = PHP_INT_MAX;
		}

		do {
			$result = hexdec(bin2hex(random_bytes($bytes))) & $bitmask;
		} while ($result > $range);

		return $result + $min;
	}
}
