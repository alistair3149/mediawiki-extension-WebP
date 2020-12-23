<?php

declare( strict_types=1 );

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

namespace MediaWiki\Extension\WebP\Hooks;

use ImagickException;
use JakubOnderka\PhpParallelLint\Exception;
use JobQueueGroup;
use MediaWiki\Extension\WebP\TransformWebPImageJob;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\FileTransformedHook;
use MediaWiki\Hook\PageMoveCompletingHook;
use MediaWiki\MediaWikiServices;
use RuntimeException;

class FileHooks implements FileTransformedHook, FileDeleteCompleteHook, PageMoveCompletingHook {

	/**
	 * @inheritDoc
	 */
	public function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ): void {
		MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->quickPurgeBatch(
			[
				WebPTransformer::changeExtensionWebp( $file->getPath() ),
			]
		);

		MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->quickCleanDir(
			$file->getPath()
		);
	}

	/**
	 * For each created thumbnail well create a webp version
	 *
	 * @inheritDoc
	 */
	public function onFileTransformed( $file, $thumb, $tmpThumbPath, $thumbPath ): void {
		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPEnableConvertOnTransform' ) === false ) {
			return;
		}

		if ( !in_array( $file->getMimeType(), WebPTransformer::$supportedMimes ) ) {
			return;
		}

		try {
			$transformer = new WebPTransformer( $file, [ 'overwrite' => true, ] );
		} catch ( RuntimeException $e ) {
			return;
		}

		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPConvertInJobQueue' ) === true ) {
			JobQueueGroup::singleton()->push(
				new TransformWebPImageJob(
					$file->getTitle(),
					[
						'title' => $file->getTitle(),
						'width' => $thumb->getWidth(),
						'height' => $thumb->getHeight(),
						'overwrite' => true,
					]
				)
			);

			return;
		}

		try {
			$transformer->transformLikeThumb( $thumb );
		} catch ( ImagickException $e ) {
			wfLogWarning( $e->getMessage() );

			return;
		}
	}

	public function onPageMoveCompleting( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		return;
		$root = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->getZonePath( 'public' );

		$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->f(
			WebPTransformer::changeExtensionWebp( $old->getText() )
		);

		throw new Exception( json_encode( $file->exists() ) );
		$file->load( \File::READ_LATEST );

		if ( $file->exists() ) {
			$file->getLocalRefPath();

			$newFile = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile(
				$new
			);

			throw new Exception( json_encode( $newFile ) );

			$status = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->getBackend()->move(
				[
					'src' => sprintf( '%s/%s', $root, $file->getLocalRefPath() ),
					'dst' => WebPTransformer::changeExtensionWebp( sprintf( '%s/%s', $root, $newFile->getLocalRefPath() ) ),
				]
			);
		} else {
			$status = \Status::newGood();
		}

		// Clear RepoGroup process cache
		MediaWikiServices::getInstance()->getRepoGroup()->clearCache( $old );
		MediaWikiServices::getInstance()->getRepoGroup()->clearCache( $new ); # clear false negative cache
		wfVarDump( $status );
		error_log( json_encode( $status ) );
	}
}
