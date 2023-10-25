<?php

namespace Comur\ImageBundle\Controller;

use Comur\ImageBundle\Handler\UploadHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class UploadController extends AbstractController
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Save uploaded image according to comur_image field configuration
     *
     * @param Request $request
     */
    public function uploadImageAction(Request $request)
    {
        $config = json_decode($request->request->get('config'), true);

        $thumbsDir = $this->getParameter('comur_image.thumbs_dir');
        $thumbSize = $this->getParameter('comur_image.media_lib_thumb_size');
        if (isset($config['uploadConfig']['uploadDir'])) {
            $uploadUrl = $this->getParameter('comur_image.public_dir') . '/' . $config['uploadConfig']['uploadDir'];
        }
        else {
            // For backward compatibility
            /**
             * @deprecated since comur/image-bundle 2.0.3 due to security issue, to be removed in 2.1. Use uploadDir instead.
             */
            $uploadUrl = $config['uploadConfig']['uploadUrl'];
        }
        $uploadUrl = substr($uploadUrl, -strlen('/')) === '/' ? $uploadUrl : $uploadUrl . '/';

        // We must use a streamed response because the UploadHandler echoes directly
        $response = new StreamedResponse();
        if ($config['uploadConfig']['generateFilename']) {
            $filename = sha1(uniqid(mt_rand(), true));
        }
        else {
            $filename = $request->files->get('image_upload_file')->getClientOriginalName();
            if (file_exists($uploadUrl . $thumbsDir . '/' . $filename)) {
                $filename = time() . '-' . $filename;
            }
        }

        $galleryDir = $this->getParameter('comur_image.gallery_dir');
        $gThumbSize = $this->getParameter('comur_image.gallery_thumb_size');

        $ext = $request->files->get('image_upload_file')->getClientOriginalExtension();
        $completeName = $filename . '.' . $ext;
        $controller = $this;

        $handlerConfig = [
            'upload_dir' => $uploadUrl,
            'param_name' => 'image_upload_file',
            'file_name' => $filename,
            'generated_file_name' => $config['uploadConfig']['generateFilename'],
            'upload_url' => $config['uploadConfig']['webDir'],
            'min_width' => $config['cropConfig']['minWidth'],
            'min_height' => $config['cropConfig']['minHeight'],
            'image_versions' => [
                'thumbnail' => [
                    'upload_dir' => $uploadUrl . $thumbsDir . '/',
                    'upload_url' => $config['uploadConfig']['webDir'] . '/' . $thumbsDir . '/',
                    'crop' => true,
                    'max_width' => $thumbSize,
                    'max_height' => $thumbSize,
                ],
            ],
        ];

        $transDomain = $this->getParameter('comur_image.translation_domain');

        $errorMessages = [
            1 => $this->translator->trans(
                'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                [],
                $transDomain
            ),
            2 => $this->translator->trans(
                'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                [],
                $transDomain
            ),
            3 => $this->translator->trans('The uploaded file was only partially uploaded', [], $transDomain),
            4 => $this->translator->trans('No file was uploaded', [], $transDomain),
            6 => $this->translator->trans('Missing a temporary folder', [], $transDomain),
            7 => $this->translator->trans('Failed to write file to disk', [], $transDomain),
            8 => $this->translator->trans('A PHP extension stopped the file upload', [], $transDomain),
            'post_max_size' => $this->translator->trans(
                'The uploaded file exceeds the post_max_size directive in php.ini',
                [],
                $transDomain
            ),
            'max_file_size' => $this->translator->trans('File is too big', [], $transDomain),
            'min_file_size' => $this->translator->trans('File is too small', [], $transDomain),
            'accept_file_types' => $this->translator->trans('Filetype not allowed', [], $transDomain),
            'max_number_of_files' => $this->translator->trans(
                'Maximum number of files exceeded',
                [],
                $transDomain
            ),
            'max_width' => $this->translator->trans('Image exceeds maximum width', [], $transDomain),
            'min_width' => $this->translator->trans(
                'Image requires a minimum width (%min%)',
                ['%min%' => $config['cropConfig']['minWidth']],
                $transDomain
            ),
            'max_height' => $this->translator->trans('Image exceeds maximum height', [], $transDomain),
            'min_height' => $this->translator->trans(
                'Image requires a minimum height (%min%)',
                ['%min%' => $config['cropConfig']['minHeight']],
                $transDomain
            ),
            'abort' => $this->translator->trans('File upload aborted', [], $transDomain),
            'image_resize' => $this->translator->trans('Failed to resize image', [], $transDomain),
        ];

        $response->setCallback(function () use ($handlerConfig, $errorMessages) {
            new UploadHandler($handlerConfig, true, $errorMessages);
        });

        return $response;
    }

    /**
     * Crop image using jCrop and upload config parameters and create thumbs if needed
     *
     * @param Request $request
     */
    public function cropImageAction(Request $request)
    {
        $config = json_decode($request->request->get('config'), true);
        $params = $request->request->all();

        $x = (int)round($params['x']);
        $y = (int)round($params['y']);
        $w = (int)round($params['w']);
        $h = (int)round($params['h']);
        $tarW = (int)round($config['cropConfig']['minWidth']);
        $tarH = (int)round($config['cropConfig']['minHeight']);

        //Issue 36
        if ($x < 0) {
            $w = $w + $x;
            $x = 0;
        }

        if ($y < 0) {
            $h = $h + $y;
            $y = 0;
        }
        //End issue 36

        $forceResize = $config['cropConfig']['forceResize'];

        if (isset($config['uploadConfig']['uploadDir'])) {
            $uploadUrl = $this->getParameter('comur_image.public_dir') . '/' .
                urldecode($config['uploadConfig']['uploadDir']);
        }
        else {
            // For backward compatibility
            /**
             * @deprecated since comur/image-bundle 2.0.3 due to security issue, to be removed in 2.1. Use uploadDir instead.
             */
            $uploadUrl = urldecode($config['uploadConfig']['uploadUrl']);
        }


        $imageName = $params['imageName'];

        $src = $uploadUrl . '/' . $imageName;

        if (!is_dir($uploadUrl . '/' . $this->getParameter('comur_image.cropped_image_dir') . '/')) {
            mkdir($uploadUrl . '/' . $this->getParameter('comur_image.cropped_image_dir') . '/', 0755, true);
        }
        $ext = pathinfo($imageName, PATHINFO_EXTENSION);
        //set uniq filename if defined inside the configuration
        if ($config['uploadConfig']['generateFilename']) {
            $imageName = sha1(uniqid(mt_rand(), true)) . '.' . $ext;
        }
        $destSrc = $uploadUrl . '/' . $this->getParameter('comur_image.cropped_image_dir') . '/' . $imageName;

        $destW = $w;
        $destH = $h;

        if ($forceResize) {

            $destW = $tarW;
            $destH = $tarH;

            if (round($w / $h, 2) != round($tarW / $tarH, 2)) {
                // var_dump($destW, $destH, $w, $h, $this->getMaxResizeValues($w, $h, $tarW, $tarH));exit;
                // $destW = $w;
                // $destH = $h;
                [$destW, $destH] = $this->getMinResizeValues($w, $h, $tarW, $tarH);
            }

        }

        $this->resizeCropImage($destSrc, $src, 0, 0, $x, $y, $destW, $destH, $w, $h);

        $galleryThumbOk = false;
        $isGallery = isset($config['uploadConfig']['isGallery']) ? $config['uploadConfig']['isGallery'] : false;
        $galleryDir = $this->getParameter('comur_image.gallery_dir');
        $gThumbSize = $this->getParameter('comur_image.gallery_thumb_size');

        if ($isGallery) {
            if (!isset($config['cropConfig']['thumbs']) || !($thumbs = $config['cropConfig']['thumbs']) ||
                !count($thumbs)) {
                $config['cropConfig']['thumbs'] = [];
            }
            $config['cropConfig']['thumbs'][] = [
                'maxWidth' => $gThumbSize,
                'maxHeight' => $gThumbSize,
                'forGallery' => true,
            ];
        }


        //Create thumbs if asked
        $previewSrc = '/' . $config['uploadConfig']['webDir'] . '/' . $this->getParameter(
                'comur_image.cropped_image_dir'
            ) . '/' . $imageName;
        if (isset($config['cropConfig']['thumbs']) && ($thumbs = $config['cropConfig']['thumbs']) && count($thumbs)) {
            $thumbDir = $uploadUrl . '/' . $this->getParameter('comur_image.cropped_image_dir') .
                '/' . $this->getParameter('comur_image.thumbs_dir') . '/';
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir);
            }


            foreach ($thumbs as $thumb) {
                $maxW = $thumb['maxWidth'];
                $maxH = $thumb['maxHeight'];

                if (!isset($thumb['forGallery']) && $maxW == $gThumbSize && $maxH == $gThumbSize) {
                    $galleryThumbOk = true;
                }
                if (isset($thumb['forGallery']) && $galleryThumbOk) {
                    continue;
                }

                [$w, $h] = $this->getMaxResizeValues($destW, $destH, $maxW, $maxH);

                $thumbName = $maxW . 'x' . $maxH . '-' . $imageName;
                $thumbSrc = $thumbDir . $thumbName;
                $this->resizeCropImage($thumbSrc, $destSrc, 0, 0, 0, 0, $w, $h, $destW, $destH);
                if (isset($thumb['useAsFieldImage']) && $thumb['useAsFieldImage']) {
                    $previewSrc = '/' . $config['uploadConfig']['webDir'] . '/' .
                        $this->getParameter('comur_image.cropped_image_dir') . '/' .
                        $this->getParameter('comur_image.thumbs_dir') . '/' . $thumbName;
                }
            }
        }

        return new Response(
            json_encode([
                'success' => true,
                'filename' => $this->getParameter('comur_image.cropped_image_dir') . '/' . $imageName,
                'previewSrc' => $previewSrc,
                'galleryThumb' => $this->getParameter('comur_image.cropped_image_dir') . '/' .
                    $this->getParameter('comur_image.thumbs_dir') . '/' .
                    $gThumbSize . 'x' . $gThumbSize . '-' . $imageName,
            ])
        );
    }

    /**
     * Calculates and returns maximum size to fit in maxW and maxH for resize
     */
    private function getMaxResizeValues($srcW, $srcH, $maxW, $maxH)
    {
        if ($srcH / $srcW < $maxH / $maxW) {
            $w = $maxW;
            $h = $srcH * ($maxW / $srcW);
        }
        else {
            $h = $maxH;
            $w = $srcW * ($maxH / $srcH);
        }

        return [$w, $h];
    }

    /**
     * Calculates and returns min size to fit in minW and minH for resize
     */
    private function getMinResizeValues($srcW, $srcH, $minW, $minH)
    {
        if ($srcH / $srcW > $minH / $minW) {
            $w = $minW;
            $h = $srcH * ($minW / $srcW);
        }
        else {
            $h = $minH;
            $w = $srcW * ($minH / $srcH);
        }

        return [$w, $h];
    }

    /**
     * Calculates and returns maximum size to fit in maxW and maxH for crop
     */
    private function getMaxCropValues($srcW, $srcH, $maxW, $maxH)
    {
        $x = $y = 0;
        if ($srcH / $srcW > $maxH / $maxW) {
            $w = $srcW;
            $h = $srcH * ($maxW / $maxH);
            $y = round($srcH - $h / 2, 0);
        }
        else {
            $h = $srcH;
            $w = $srcW * ($maxH / $maxW);
            $x = round($srcW - $w / 2, 0);
        }

        return [$w, $h, $x, $y];
    }

    /**
     * Returns files from required directory
     *
     * @param Request $request
     */
    public function getLibraryImagesAction(Request $request)
    {
        $finder = new Finder();

        $finder->sortByType();
        $finder->depth('== 0');
        $result = [];
        $files = [];

        $result['thumbsDir'] = $this->getParameter('comur_image.thumbs_dir');

        $libDir = $this->getParameter('comur_image.public_dir') . '/' . $request->request->get('dir');

        if (!is_dir($libDir)) {
            mkdir($libDir . '/', 0755, true);
        }

        foreach ($finder->in($libDir)->files() as $file) {
            $files[] = $file->getFilename();
        }
        $result['files'] = $files;

        // var_dump(json_encode($result));exit;

        return new Response(json_encode($result));
    }

    private function isGifAnimated($filename)
    {
        if (!($fh = @fopen($filename, 'rb'))) {
            return false;
        }
        $count = 0;
        //an animated gif contains multiple "frames", with each frame having a
        //header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

        // We read through the file til we reach the end of the file, or we've found
        // at least 2 frame headers
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
        }

        fclose($fh);

        return $count > 1;
    }

    /**
     * Crops or resizes image and writes it on disk
     */
    private function resizeCropImage($destSrc, $imgSrc, $destX, $destY, $srcX, $srcY, $destW, $destH, $srcW, $srcH)
    {
        $type = strtolower(pathinfo($imgSrc, PATHINFO_EXTENSION));

        switch ($type) {
            case 'jpg':
            case 'jpeg':
                $srcFunc = 'imagecreatefromjpeg';
                $writeFunc = 'imagejpeg';
                $imageQuality = 100;
                break;
            case 'gif':
                if ($this->isGifAnimated($imgSrc) && extension_loaded('imagick')) {
                    $image = new \Imagick($imgSrc);

                    $image = $image->coalesceImages();

                    foreach ($image as $frame) {
                        $frame->cropImage($srcW, $srcH, $srcX, $srcY);
                        $frame->thumbnailImage($destW, $destH);
                        $frame->setImagePage($destW, $destH, $destX, $destY);
                    }

                    $image = $image->deconstructImages();
                    $image->writeImages($destSrc, true);

                    return false;
                }
                else {
                    $srcFunc = 'imagecreatefromgif';
                    $writeFunc = 'imagegif';
                    $imageQuality = null;
                }
                break;
            case 'png':
                $srcFunc = 'imagecreatefrompng';
                $writeFunc = 'imagepng';
                $imageQuality = 9;
                break;
            default:
                return false;
        }

        $imgR = $srcFunc($imgSrc);

        if (round($srcW / $srcH, 2) != round($destW / $destH, 2)) {
            $destW = $srcW;
            $destH = $srcH;
        }
        $dstR = imagecreatetruecolor($destW, $destH);

        if ($type == 'png') {
            imagealphablending($dstR, false);
            imagesavealpha($dstR, true);
        }

        imagecopyresampled($dstR, $imgR, $destX, $destY, $srcX, $srcY, $destW, $destH, $srcW, $srcH);

        switch ($type) {
            case 'gif':
            case 'png':
                imagecolortransparent($dstR, imagecolorallocate($dstR, 0, 0, 0));
            case 'png':
                imagealphablending($dstR, false);
                imagesavealpha($dstR, true);
                break;
        }

        $writeFunc($dstR, $destSrc, $imageQuality);
    }

    /**
     * returns translation catalogue to add it for javascript translation support
     * @param Request $request
     * @return Response
     */
    public function getTranslationCatalogue(Request $request)
    {
        $transDomain = $this->getParameter('comur_image.translation_domain');
        $catalogue = $this->translator->getCatalogue($request->getLocale());
        $messages = $catalogue->all();

        return $this->render('@ComurImage/translations.html.twig', [
            'messages' => $messages[$transDomain],
        ]);
    }
}
