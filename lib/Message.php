<?php

namespace Amp\Websocket;

use Amp\Promise;
use Amp\PromiseStream;

class Message extends PromiseStream implements Promise {
	private $promise;
	private $whens = [];
	private $string;

	public function __construct(Promise $promise) {
		parent::__construct($promise);
		$this->promise = $promise;
		$when = function ($e, $bool) use (&$continue) {
			$continue = $bool;
		};
		$promise->when(function() use (&$continue, $when) {
			$this->valid()->when($when);
			while ($continue) {
				$string[] = $this->consume();
				$this->valid()->when($when);
			}

			if (isset($string)) {
				if (isset($string[1])) {
					$string = implode($string);
				} else {
					$string = $string[0];
				}
			} else {
				$string = "";
			}
			$this->string = $string;

			foreach ($this->whens as list($when, $data)) {
				$when(null, $string, $data);
			}
		});
	}

	public function when(callable $func, $data = null) {
		if (isset($this->string)) {
			$func(null, $this->string, $data);
		} else {
			$this->whens[] = [$func, $data];
		}
		return $this;
	}

	public function watch(callable $func, $data = null) {
		$this->promise->watch($func, $data);
		return $this;
	}
}
