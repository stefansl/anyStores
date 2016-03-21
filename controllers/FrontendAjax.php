<?php // with ♥ and Contao

/**
 * anyStores for Contao Open Source CMS
 *
 * @copyright   (c) 2016 Tastaturberuf <mail@tastaturberuf.de>
 * @author      Daniel Jahnsmüller <mail@jahnsmueller.net>
 * @license     http://opensource.org/licenses/lgpl-3.0.html
 * @package     anyStores
 * @since       1.8.0
 */


namespace Tastaturberuf;


class FrontendAjax extends \Controller
{

    /**
     * FrontendAjax constructor.
     *
     * @param int $intModuleId
     */
    public static function run($intModuleId)
    {
        $objModule = \ModuleModel::findByPk($intModuleId);

        if (!$objModule || $objModule->type !== 'anystores_map')
        {
            static::respond(array('status' => 'error', 'message' => 'Invalid module'));
        }

        // Hook to manipulate the module
        if (isset($GLOBALS['TL_HOOKS']['anystores_getAjaxModule']) && is_array($GLOBALS['TL_HOOKS']['anystores_getAjaxModule']))
        {
            foreach ($GLOBALS['TL_HOOKS']['anystores_getAjaxModule'] as $callback)
            {
                \System::importStatic($callback[0])->{$callback[1]}($objModule);
            }
        }

        if (\Validator::isBinaryUuid($objModule->anystores_defaultMarker))
        {
            if (($objFile = \FilesModel::findByPk($objModule->anystores_defaultMarker)) !== null)
            {
                $objModule->anystores_defaultMarker = $objFile->path;
            }
        }

        // Find stores
        $objStores = AnyStoresModel::findPublishedByCategory(deserialize($objModule->anystores_categories));

        if (!$objStores)
        {
            static::respond(array('status'  => 'error', 'message' => 'No stores found'));
        }

        while ($objStores->next())
        {
            // generate jump to
            if ($objModule->jumpTo)
            {
                if (($objLocation = \PageModel::findByPk($objModule->jumpTo)) !== null)
                {
                    //@todo language parameter
                    $strStoreKey   = !$GLOBALS['TL_CONFIG']['useAutoItem'] ? '/store/' : '/';
                    $strStoreValue = $objStores->alias;

                    $objStores->href = \Controller::generateFrontendUrl($objLocation->row(), $strStoreKey.$strStoreValue);
                }
            }

            // Encode email
            $objStores->email = \String::encodeEmail($objStores->email);

            // Encode opening times
            $objStores->opening_times = deserialize($objStores->opening_times);

            // decode logo
            if (\Validator::isBinaryUuid($objStores->logo))
            {
                if (($objFile = \FilesModel::findByPk($objStores->logo)) !== null)
                {
                    $objStores->logo = $objFile->path;
                }
            }

            // decode marker
            if (\Validator::isBinaryUuid($objStores->marker))
            {
                if (($objFile = \FilesModel::findByPk($objStores->marker)) !== null)
                {
                    $objStores->marker = $objFile->path;
                }
            }

            // add category marker
            //@todo make null
            $objStores->categoryMarker = false;

            if (($objCategory = AnyStoresCategoryModel::findByPk($objStores->pid)) !== null)
            {
                if (\Validator::isBinaryUuid($objCategory->defaultMarker))
                {
                    if (($objFile = \FilesModel::findByPk($objCategory->defaultMarker)) !== null)
                    {
                        $objStores->categoryMarker = $objFile->path;
                    }
                }
            }

            // render html
            $strTemplate = $objModule->anystores_detailTpl ?: 'anystores_details';
            $objTemplate = new \FrontendTemplate($strTemplate);
            $objTemplate->setData($objStores->current()->row());

            $objStores->tiphtml = static::replaceInsertTags($objTemplate->parse());
        }

        $arrRespond = array
        (
            'status' => 'success',
            'count'  => (int) $objStores->count(),
            'module' => array
            (
                'latitude'      => (float) $objModule->anystores_latitude,
                'longitude'     => (float) $objModule->anystores_longitude,
                'zoom'          => (int) $objModule->anystores_zoom,
                'streetview'    => (bool) $objModule->anystores_streetview,
                'maptype'       => (string) $objModule->anystores_maptype,
                'defaultMarker' => (string) $objModule->anystores_defaultMarker
            ),
            'stores' => $objStores->fetchAll()
        );

        // decode global default marker
        $arrRespond['global']['defaultMarker'] = null;

        if (\Validator::isUuid(\Config::get('anystores_defaultMarker')))
        {
            if (($objFile = \FilesModel::findByPk(\Config::get('anystores_defaultMarker'))) !== null)
            {
                $arrRespond['global']['defaultMarker'] = $objFile->path;
            }
        }

        static::respond($arrRespond);
    }


    protected static function respond($arrRespond)
    {
        $strRespose = json_encode($arrRespond);

        header('Content-Type: application/json');

        if ( $strRespose )
        {
            echo $strRespose;
        }
        else
        {
            echo json_encode(array('status' => 'error', 'message' => json_last_error_msg()));
        }

        exit;
    }

}
