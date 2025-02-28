<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShop\PrestaShop\Core\ConstraintValidator;

use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Address\Configuration\AddressConstraint;
use PrestaShop\PrestaShop\Core\Domain\Currency\ValueObject\AlphaIsoCode;
use PrestaShop\PrestaShop\Core\Domain\Language\ValueObject\IsoCode;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\Ean13;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\Isbn;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\Reference;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\Upc;
use PrestaShop\PrestaShop\Core\Domain\State\StateSettings;
use ReflectionClass;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates specific regex pattern for provided type
 */
class TypedRegexValidator extends ConstraintValidator
{
    public const CATALOG_CHARS = '<>;=#{}';
    public const GENERIC_NAME_CHARS = '<>={}';
    public const MESSAGE_CHARS = '<>{}';
    public const NAME_CHARS = '0-9!<>,;?=+()@#"�{}_$%:';

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof TypedRegex) {
            throw new UnexpectedTypeException($constraint, TypedRegex::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $pattern = $this->getPattern($constraint->type);
        $value = $this->sanitize($value, $constraint->type);

        if (!$this->match($pattern, $constraint->type, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setTranslationDomain('Admin.Notifications.Error')
                ->setParameter('%s', $this->formatValue($value))
                ->addViolation()
            ;
        }
    }

    /**
     * Returns regex pattern that depends on type
     *
     * @param string $type
     *
     * @return string
     */
    private function getPattern($type)
    {
        switch ($type) {
            case TypedRegex::TYPE_NAME:
                return '/^[^0-9!<>,;?=+()@#"°{}_$%:¤|]*$/u';
            case TypedRegex::TYPE_CATALOG_NAME:
                return '/^[^<>;=#{}]*$/u';
            case TypedRegex::TYPE_GENERIC_NAME:
                return '/^[^<>={}]*$/u';
            case TypedRegex::TYPE_CITY_NAME:
                return '/^[^!<>;?=+@#"°{}_$%]*$/u';
            case TypedRegex::TYPE_ADDRESS:
                return '/^[^!<>?=+@{}_$%]*$/u';
            case TypedRegex::TYPE_POST_CODE:
                return '/^[a-zA-Z 0-9-]+$/';
            case TypedRegex::TYPE_PHONE_NUMBER:
                return '/^[+0-9. ()\/-]*$/';
            case TypedRegex::TYPE_MESSAGE:
                return '/[<>{}]/i';
            case TypedRegex::TYPE_LANGUAGE_ISO_CODE:
                return IsoCode::PATTERN;
            case TypedRegex::TYPE_LANGUAGE_CODE:
                return '/^[a-zA-Z]{2}(-[a-zA-Z]{2})?$/';
            case TypedRegex::TYPE_CURRENCY_ISO_CODE:
                return AlphaIsoCode::PATTERN;
            case TypedRegex::TYPE_FILE_NAME:
                return '/^[a-zA-Z0-9_.-]+$/';
            case TypedRegex::TYPE_DNI_LITE:
                return AddressConstraint::DNI_LITE_PATTERN;
            case TypedRegex::TYPE_STATE_ISO_CODE:
                return StateSettings::STATE_ISO_CODE_PATTERN;
            case TypedRegex::TYPE_UPC:
                return Upc::VALID_PATTERN;
            case TypedRegex::TYPE_EAN_13:
                return Ean13::VALID_PATTERN;
            case TypedRegex::TYPE_ISBN:
                return Isbn::VALID_PATTERN;
            case TypedRegex::TYPE_REFERENCE:
                return Reference::VALID_PATTERN;
            case TypedRegex::TYPE_MODULE_NAME:
                return '/^[a-zA-Z0-9_-]+$/';
            case TypedRegex::TYPE_URL:
                return '/^[~:#,$%&_=\(\)\.\? \+\-@\/a-zA-Z0-9\pL\pS-]+$/u';
            case TypedRegex::TYPE_WEBSERVICE_KEY:
                return '/^[a-zA-Z0-9@\#\?\-\_]+$/i';
            default:
                $definedTypes = implode(', ', array_values((new ReflectionClass(TypedRegex::class))->getConstants()));
                throw new InvalidArgumentException(sprintf('Type "%s" is not defined. Defined types are: %s', $type, $definedTypes));
        }
    }

    /**
     * Responsible for sanitizing the string depending on type. (eg. applying  stripslashes())
     *
     * @param string $value
     * @param string $type
     *
     * @return string
     */
    private function sanitize($value, $type)
    {
        if ($type === TypedRegex::TYPE_NAME) {
            $value = stripslashes($value);
        }

        return $value;
    }

    /**
     * Responsible for applying preg_match depending on type.
     * preg_match returns 1 if the pattern
     * matches given subject, 0 if it does not, or FALSE
     * if an error occurred.
     *
     * @param string $pattern
     * @param string $type
     * @param string $value
     *
     * @return bool|int
     */
    private function match($pattern, $type, $value)
    {
        $match = preg_match($pattern, $value);

        $typesToInverseMatching = [TypedRegex::TYPE_MESSAGE];
        if (in_array($type, $typesToInverseMatching, true)) {
            return !$match;
        }

        return $match;
    }
}
