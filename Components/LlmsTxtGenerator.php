<?php

namespace DtgsLllmstxtGenerator\Components;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Models\Shop\Shop;

class LlmsTxtGenerator
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param ContainerInterface $container
     * @param Connection $connection
     */
    public function __construct(ContainerInterface $container, Connection $connection)
    {
        $this->container = $container;
        $this->connection = $connection;
    }

    /**
     * Generates the complete llms.txt content for the current shop.
     *
     * @return string
     */
    public function generate()
    {
        /** @var Shop $shop */
        $shop = $this->container->get('shop');

        $config = $this->container->get('shopware.plugin.cached_config_reader')
            ->getByPluginName('DtgsLllmstxtGenerator', $shop);

        $shopName = $shop->getName();
        $baseUrl = $this->getBaseUrl($shop);

        $lines = [];
        $lines[] = '# ' . $shopName;
        $lines[] = '';
        $lines[] = '> ' . $this->getShopDescription($shop, $shopName);
        $lines[] = '';

        if (!empty($config['additional_info'])) {
            $lines[] = $config['additional_info'];
            $lines[] = '';
        }

        $lines[] = '## Shop-Informationen';
        $lines[] = '';
        $lines[] = '- Name: ' . $shopName;
        $lines[] = '- URL: ' . $baseUrl;
        $lines[] = '- Sprache (Locale): ' . $shop->getLocale()->getLocale();
        if ($shop->getCurrency()) {
            $lines[] = '- Währung: ' . $shop->getCurrency()->getCurrency();
        }
        $lines[] = '';

        if (!empty($config['include_categories'])) {
            $includeDescription = !empty($config['include_category_description']);
            $categorySection = $this->getCategorySection($shop, $baseUrl, $includeDescription);
            if ($categorySection) {
                $lines[] = '## Kategorien';
                $lines[] = '';
                $lines = array_merge($lines, $categorySection);
                $lines[] = '';
            }
        }

        if (!empty($config['include_products'])) {
            $maxProducts = isset($config['max_products']) ? (int) $config['max_products'] : 200;
            if ($maxProducts <= 0) {
                $maxProducts = 200;
            }
            $productSection = $this->getProductSection($shop, $baseUrl, $maxProducts);
            if ($productSection) {
                $lines[] = '## Produkte';
                $lines[] = '';
                $lines = array_merge($lines, $productSection);
                $lines[] = '';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param Shop $shop
     * @param string $fallback
     * @return string
     */
    private function getShopDescription(Shop $shop, $fallback)
    {
        // Try to read the SEO/meta description from the shop's root category.
        $rootCategoryId = $shop->getCategory() ? $shop->getCategory()->getId() : null;
        if ($rootCategoryId) {
            $metaDescription = $this->connection->fetchColumn(
                'SELECT metadescription FROM s_categories WHERE id = :id',
                ['id' => $rootCategoryId]
            );
            if (!empty($metaDescription)) {
                return trim($metaDescription);
            }
        }

        return 'Online-Shop ' . $fallback;
    }

    /**
     * @param Shop $shop
     * @param string $baseUrl
     * @param bool $includeDescription
     * @return array
     */
    private function getCategorySection(Shop $shop, $baseUrl, $includeDescription = true)
    {
        $rootCategoryId = $shop->getCategory() ? $shop->getCategory()->getId() : 1;

        $categories = $this->connection->fetchAll(
            'SELECT c.id, c.description, c.path, c.metadescription, c.cmstext
             FROM s_categories c
             WHERE c.active = 1
               AND c.path LIKE :path
             ORDER BY c.parent, c.position',
            ['path' => '%|' . $rootCategoryId . '|%']
        );

        $lines = [];
        foreach ($categories as $category) {
            if (empty($category['description'])) {
                continue;
            }
            $depth = $category['path'] ? substr_count($category['path'], '|') : 1;
            $indent = str_repeat('  ', max(0, $depth - 2));

            $url = $this->getSeoUrl(
                'sViewport=cat&sCategory=' . (int) $category['id'],
                $baseUrl,
                $shop->getId()
            );

            $line = $indent . '- ' . trim($category['description']);
            if ($url !== '') {
                $line .= ' [' . $url . ']';
            }

            if ($includeDescription) {
                $description = '';
                if (!empty($category['metadescription'])) {
                    $description = $category['metadescription'];
                } elseif (!empty($category['cmstext'])) {
                    $description = strip_tags($category['cmstext']);
                }
                $description = trim(preg_replace('/\s+/', ' ', $description));
                if ($description !== '') {
                    $line .= ': ' . $description;
                }
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param Shop $shop
     * @param string $baseUrl
     * @param int $limit
     * @return array
     */
    private function getProductSection(Shop $shop, $baseUrl, $limit)
    {
        $products = $this->connection->fetchAll(
            'SELECT a.id, a.name, d.ordernumber, a.description_long, a.description
             FROM s_articles a
             INNER JOIN s_articles_details d ON d.id = a.main_detail_id
             WHERE a.active = 1
             ORDER BY a.id
             LIMIT ' . (int) $limit
        );

        $lines = [];
        foreach ($products as $product) {
            $name = trim($product['name']);
            if ($name === '') {
                continue;
            }

            $summary = '';
            if (!empty($product['description'])) {
                $summary = $product['description'];
            } elseif (!empty($product['description_long'])) {
                $summary = strip_tags($product['description_long']);
            }
            $summary = trim(preg_replace('/\s+/', ' ', $summary));
            if (mb_strlen($summary) > 200) {
                $summary = mb_substr($summary, 0, 197) . '...';
            }

            $line = '- ' . $name;
            if (!empty($product['ordernumber'])) {
                $line .= ' (Art.-Nr.: ' . $product['ordernumber'] . ')';
            }

            $url = $this->getSeoUrl(
                'sViewport=detail&sArticle=' . (int) $product['id'],
                $baseUrl,
                $shop->getId()
            );
            if ($url !== '') {
                $line .= ' [' . $url . ']';
            }

            if ($summary !== '') {
                $line .= ': ' . $summary;
            }
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Resolves the SEO url for the given internal org path. Falls back to the
     * canonical query url if no SEO url is available.
     *
     * @param string $orgPath
     * @param string $baseUrl
     * @param int $shopId
     * @return string
     */
    private function getSeoUrl($orgPath, $baseUrl, $shopId)
    {
        $seoPath = $this->connection->fetchColumn(
            'SELECT path FROM s_core_rewrite_urls
             WHERE org_path = :orgPath AND main = 1 AND subshopID = :shopId
             ORDER BY id DESC LIMIT 1',
            ['orgPath' => $orgPath, 'shopId' => (int) $shopId]
        );

        if (!empty($seoPath)) {
            return rtrim($baseUrl, '/') . '/' . ltrim($seoPath, '/');
        }

        if ($baseUrl === '') {
            return '';
        }

        return rtrim($baseUrl, '/') . '/?' . $orgPath;
    }

    /**
     * @param Shop $shop
     * @return string
     */
    private function getBaseUrl(Shop $shop)
    {
        $host = $shop->getHost();
        if (!$host) {
            return '';
        }

        $scheme = $shop->getSecure() ? 'https://' : 'http://';
        $basePath = $shop->getBasePath() ? rtrim($shop->getBasePath(), '/') : '';

        return $scheme . $host . $basePath;
    }
}
