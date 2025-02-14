<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\WebP\Transformer;

use File;
use Status;

interface MediaTransformer {

	/**
	 * @param File $file The file that should be transformed
	 * @param array $options Array of options
	 */
	public function __construct( File $file, array $options = [] );

	/**
	 * Transform the image like the passed thumbnail image
	 *
	 * @param int $width Width of the thumb, height is inferred automatically
	 * @return Status
	 */
	public function transformLikeThumb( int $width ): Status;

	/**
	 * Transform the source image
	 *
	 * @return Status
	 */
	public function transform(): Status;

	/**
	 * Change the image extension to the one of the transformer, e.g. 'webp'
	 *
	 * @param string $path
	 * @return string
	 */
	public static function changeExtension( string $path ): string;

	/**
	 * Check if a file can be transformed by this transformer
	 *
	 * @param File $file
	 * @return bool
	 */
	public static function canTransform( File $file ): bool;

	/**
	 * The file extension, used as the subdirectory name where images from this transformer are stored
	 * E.g. /images/<Folder>, or /images/thumbs/<Folder>
	 *
	 * @return string
	 */
	public static function getFileExtension(): string;

	/**
	 * The mime type of the transformed image
	 *
	 * @return string
	 */
	public static function getMimeType(): string;
}
