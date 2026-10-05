<?php

namespace App\Services\AI\Editor;

/**
 * One operation in a plan could not be applied — the rest still are.
 *
 * Deliberately NOT a RuntimeException: a RuntimeException thrown out of a
 * build fails the whole generation and refunds the charge, which is right
 * when nothing could be done and wrong when four changes out of five
 * landed. This one is caught per operation, recorded with its reason, and
 * shown to the creator alongside what did work.
 */
class SkippedOperation extends \Exception
{
}
