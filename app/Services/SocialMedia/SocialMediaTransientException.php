<?php

namespace App\Services\SocialMedia;

/** 429 / timeout / http 5xx — bisa retry. */
class SocialMediaTransientException extends SocialMediaScraperException
{
}
