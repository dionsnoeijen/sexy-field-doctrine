<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

class TransformResults implements TransformResultsInterface
{
    public function intoHierarchy(array $results): array
    {
        $hierarchy = [];
        foreach ($results as $result) {
            $hierarchy[] = $this->explodeTree($result);
        }
        return $hierarchy;
    }

    /**
     * Takes an array like this:
     * [
     *     'all_i_need_is_hierarchy' => 'allright',
     *     'all_i_need_is_structure' => 'and',
     *     'here_it_is' => 'thanks'
     * ]
     *
     * And transforms it into this:
     * [
     *     'all' => [
     *          'i' => [
     *              'need' => [
     *                  'is' => [
     *                      'hierarchy' => 'allright',
     *                      'structure' => 'and'
     *                  ]
     *              ]
     *          ]
     *     ],
     *     'here' => [
     *          'it' => [
     *              'is' => 'thanks'
     *          ]
     *     ]
     * ]
     *
     * @param array $array
     * @param string $delimiter
     * @param bool $baseval
     * @return array
     */
    private function explodeTree(array $array, string $delimiter = '_', bool $baseval = false): array
    {
        $splitRegularExpression   = '/' . preg_quote($delimiter, '/') . '/';
        $result = [];

        foreach ($array as $key => $val) {
            $parts = preg_split($splitRegularExpression, $key, -1, PREG_SPLIT_NO_EMPTY);
            $leaf = array_pop($parts);
            $parent = &$result;

            foreach ($parts as $part) {
                if (!isset($parent[$part])) {
                    $parent[$part] = [];
                } elseif (!is_array($parent[$part])) {
                    if ($baseval) {
                        $parent[$part] = ['__base_val' => $parent[$part]];
                    } else {
                        $parent[$part] = [];
                    }
                }
                $parent = &$parent[$part];
            }

            if (empty($parent[$leaf])) {
                $parent[$leaf] = $val;
            } elseif ($baseval && is_array($parent[$leaf])) {
                $parent[$leaf]['__base_val'] = $val;
            }
        }
        return $result;
    }
}
