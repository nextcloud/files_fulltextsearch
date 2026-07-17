<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\FullTextSearch\Model;

use JsonSerializable;
use OCP\FullTextSearch\Model\ISearchOption;

/**
 * @since 15.0.0
 *
 * Class ISearchOption
 *
 * @package OC\FullTextSearch\Model
 */
final class SearchOption implements ISearchOption, JsonSerializable {

	/**
	 * ISearchOption constructor.
	 *
	 * Some value can be set during the creation of the object.
	 *
	 * @since 15.0.0
	 */
	public function __construct(
		private string $name = '',
		private string $title = '',
		private string $type = '',
		private string $size = '',
		private string $placeholder = '',
	) {
	}

	/**
	 * Set the name/key of the option.
	 * The string should only contain alphanumerical chars and underscore.
	 * The key can be retrieved when using ISearchRequest::getOption
	 *
	 * @see ISearchRequest::getOption
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function setName(string $name): ISearchOption {
		$this->name = $name;

		return $this;
	}

	/**
	 * Get the name/key of the option.
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Set the title/display name of the option.
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function setTitle(string $title): ISearchOption {
		$this->title = $title;

		return $this;
	}

	/**
	 * Get the title of the option.
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * Set the type of the option.
	 * $type can be ISearchOption::CHECKBOX or ISearchOption::INPUT
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function setType(string $type): ISearchOption {
		$this->type = $type;

		return $this;
	}

	/**
	 * Get the type of the option.
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function getType(): string {
	}

	/**
	 * In case of Type is INPUT, set the size of the input field.
	 * Value can be ISearchOption::INPUT_SMALL or not defined.
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function setSize(string $size): ISearchOption {
	}

	/**
	 * Get the size of the INPUT.
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function getSize(): string {
	}

	/**
	 * In case of Type is , set the placeholder to be displayed in the input
	 * field.
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function setPlaceholder(string $placeholder): ISearchOption {
	}

	/**
	 * Get the placeholder.
	 *
	 * @since 15.0.0
	 */
	#[\Override]
	public function getPlaceholder(): string {
	}

	/**
	 * @since 15.0.0
	 */
	#[\Override]
	public function jsonSerialize(): array {
	}
}
