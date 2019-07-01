<?php

declare(strict_types=1);

namespace Shopsys\FrameworkBundle\Component\ArrayUtils;

class RecursiveArraySorter
{
    /**
     * @param array $array
     * @return bool
     */
    public function recursiveArrayKsort(array &$array): bool
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveArrayKsort($value);
            }
        }
        return ksort($array);
    }
}
