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

namespace PrestaShop\PrestaShop\Adapter\Category\CommandHandler;

use Category;
use PrestaShop\PrestaShop\Adapter\Domain\AbstractObjectModelHandler;
use PrestaShop\PrestaShop\Adapter\Image\Uploader\CategoryImageUploader;
use PrestaShop\PrestaShop\Core\CommandBus\Attributes\AsCommandHandler;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\AddCategoryCommand;
use PrestaShop\PrestaShop\Core\Domain\Category\CommandHandler\AddCategoryHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CannotAddCategoryException;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\MenuThumbnailsLimitException;
use PrestaShop\PrestaShop\Core\Domain\Category\ValueObject\CategoryId;
use PrestaShop\PrestaShop\Core\Domain\Category\ValueObject\MenuThumbnailId;

/**
 * Adds new category using legacy object model.
 *
 * @internal
 */
#[AsCommandHandler]
final class AddCategoryHandler extends AbstractObjectModelHandler implements AddCategoryHandlerInterface
{
    /**
     * @var CategoryImageUploader
     */
    private $categoryImageUploader;

    public function __construct(CategoryImageUploader $categoryImageUploader)
    {
        $this->categoryImageUploader = $categoryImageUploader;
    }

    /**
     * {@inheritdoc}
     *
     * @param AddCategoryCommand $command
     *
     * @return CategoryId
     */
    public function handle(AddCategoryCommand $command)
    {
        if (count($command->getMenuThumbnailImages()) > count(MenuThumbnailId::ALLOWED_ID_VALUES)) {
            throw new MenuThumbnailsLimitException('Maximum number of menu thumbnails exceeded for new category');
        }
        $category = $this->createCategoryFromCommand($command);

        $categoryId = new CategoryId((int) $category->id);

        $this->categoryImageUploader->uploadImages(
            $categoryId,
            $command->getCoverImage(),
            $command->getThumbnailImage(),
            $command->getMenuThumbnailImages()
        );

        return $categoryId;
    }

    /**
     * @param AddCategoryCommand $command
     *
     * @return Category
     *
     * @throws CannotAddCategoryException
     * @throws MenuThumbnailsLimitException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function createCategoryFromCommand(AddCategoryCommand $command)
    {
        $category = new Category();
        $category->id_parent = $command->getParentCategoryId();
        $category->active = $command->isActive();

        if (null !== $command->getLocalizedNames()) {
            $category->name = $command->getLocalizedNames();
        }

        if (null !== $command->getLocalizedLinkRewrites()) {
            $category->link_rewrite = $command->getLocalizedLinkRewrites();
        }

        if (null !== $command->getLocalizedDescriptions()) {
            $category->description = $command->getLocalizedDescriptions();
        }

        if (null !== $command->getLocalizedAdditionalDescriptions()) {
            $category->additional_description = $command->getLocalizedAdditionalDescriptions();
        }

        if (null !== $command->getLocalizedMetaTitles()) {
            $category->meta_title = $command->getLocalizedMetaTitles();
        }

        if (null !== $command->getLocalizedMetaDescriptions()) {
            $category->meta_description = $command->getLocalizedMetaDescriptions();
        }

        if (null !== $command->getLocalizedMetaKeywords()) {
            $category->meta_keywords = $command->getLocalizedMetaKeywords();
        }

        if (null !== $command->getAssociatedGroupIds()) {
            $category->groupBox = $command->getAssociatedGroupIds();
        }

        if (false === $category->validateFields(false)) {
            throw new CannotAddCategoryException('Invalid data for creating category.');
        }

        if (false === $category->validateFieldsLang(false)) {
            throw new CannotAddCategoryException('Invalid language data for creating category.');
        }

        if (false === $category->add()) {
            throw new CannotAddCategoryException('Failed to add new category.');
        }

        if ($command->getAssociatedShopIds()) {
            $this->associateWithShops($category, $command->getAssociatedShopIds());
        }

        return $category;
    }
}
