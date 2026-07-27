<?php

namespace App\Domain\Campaign\Support;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class CampaignContentSchema
{
    /** @param array<string,mixed> $input
     * @return array{headline:string,summary:string,cta_label:string,schema_version:int} */
    public function normalize(array $input): array
    {
        $v = Validator::make($input, ['headline' => ['required', 'string', 'max:255'], 'summary' => ['required', 'string', 'max:2000'], 'cta_label' => ['required', 'string', 'max:80']])->validate();
        $result = ['headline' => trim($v['headline']), 'summary' => trim($v['summary']), 'cta_label' => trim($v['cta_label']), 'schema_version' => 1];
        foreach (['headline', 'summary', 'cta_label'] as $key) {
            if ($result[$key] === '' || $result[$key] !== strip_tags($result[$key])) {
                throw new InvalidArgumentException('Campaign content must be non-empty plain text.');
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $value */
    public function checksum(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
