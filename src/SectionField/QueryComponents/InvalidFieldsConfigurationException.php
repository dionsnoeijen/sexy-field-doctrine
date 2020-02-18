<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Throwable;

class InvalidFieldsConfigurationException extends \Exception
{
    public function __construct(
        $message = "This fields configuration cannot be transformed into DQL",
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
