<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thrown when a business rule blocks an operation (stock, status, permissions).
 */
final class BusinessException extends \RuntimeException
{
}
