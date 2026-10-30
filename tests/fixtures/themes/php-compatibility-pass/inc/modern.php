<?php
declare(strict_types=1);

enum Fixture_Suit: string {
	case Hearts = 'h';
	case Spades = 's';
}

final class Fixture_Card {
	public function __construct(
		public readonly Fixture_Suit $suit,
		public readonly ?string $label = null,
	) {}

	public function name( ?string $prefix = null ): string {
		return ( $prefix ?? '' ) . "{$this->suit->value}";
	}
}
