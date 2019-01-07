<?php
/**
 * Created by PhpStorm.
 * User: franzwilding
 * Date: 2019-01-07
 * Time: 10:52
 */

namespace UniteCMS\CoreBundle\Expression;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class UniteExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    static function generateSlug($string) {
        
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    }

    /**
     * @return ExpressionFunction[] An array of Function instances
     */
    public function getFunctions()
    {
        return [

            // A string slug function.
            new ExpressionFunction('slug', function ($str) {
                return sprintf('(is_string(%1$s) ? call_user_func("'.(static::class).'::generateSlug", %1$s) : %1$s)', $str);
            }, function ($arguments, $str) {
                if (!is_string($str)) {
                    return $str;
                }
                return static::generateSlug($str);
            }),
        ];
    }
}