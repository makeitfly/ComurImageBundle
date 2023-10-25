<?php

namespace Comur\ImageBundle\Form\Type;

use Symfony\Component\Form\AbstractType;

// use Symfony\Component\Form\FormBuilder;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CroppableImageType extends AbstractType
{

    protected $isGallery = false;
    protected $galleryDir = null;
    protected $thumbsDir = null;

    static $uploadConfig = [
        'uploadRoute' => 'comur_api_upload',
        'uploadUrl' => null, // DEPRECATED
        'uploadDir' => null,
        'webDir' => null,
        'fileExt' => '*.jpg;*.gif;*.png;*.jpeg',
        'maxFileSize' => 50,
        'libraryDir' => null,
        'libraryRoute' => 'comur_api_image_library',
        'showLibrary' => true,
        'saveOriginal' => false, //save original file name
        'generateFilename' => true, //generate an uniq filename
    ];

    static $cropConfig = [
        // 'disableCrop' => false,
        'minWidth' => 1,
        'minHeight' => 1,
        'aspectRatio' => true,
        'cropRoute' => 'comur_api_crop',
        'forceResize' => false,
        'thumbs' => null,
        'disable' => false,
    ];

    // public function getParent()
    // {
    //     return 'text';
    // }

    public function getBlockPrefix(): string
    {
        return 'comur_image';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        // if($options['uploadConfig']['saveOriginal']){
        //     $form->getParent()->add($options['uploadConfig']['saveOriginal'], 'hidden');
        // }
        // var_dump($builder->getDataMapper());exit;
        if ($options['uploadConfig']['saveOriginal']) {
            $builder->add($options['uploadConfig']['saveOriginal'], TextType::class, [
                // 'inherit_data' => true,
                // 'property_path' => $options['uploadConfig']['saveOriginal'],
                'attr' => ['style' => 'opacity: 0;width: 0; max-width: 0; height: 0; max-height: 0;'],
            ]);
        }
        $builder->add($builder->getName(), TextType::class, [
            // 'property_path' => $builder->getName(),
            // 'inherit_data' => true,
            'attr' => ['style' => 'opacity: 0;width: 0; max-width: 0; height: 0; max-height: 0;'],
        ]);
    }

    /**
     * Returns upload config normalizer. It can be used by compatible bundles to normalize parameters
     * @param $uploadConfig
     * @param $isGallery
     * @param $galleryDir
     * @return \Closure
     */
    public static function getUploadConfigNormalizer($uploadConfig, $isGallery = false, $galleryDir = null)
    {
        return function (Options $options, $value) use ($uploadConfig, $isGallery, $galleryDir) {
            $config = array_merge($uploadConfig, $value);

            if ($isGallery) {
                if ($config['uploadDir']) {
                    $config['uploadDir'] = $config['uploadDir'] . '/' . $galleryDir;
                }
                else {
                    /**
                     * @deprecated since comur/image-bundle 2.0.3 due to security issue, to be removed in 2.1. Use uploadDir instead.
                     */
                    $config['uploadUrl'] = $config['uploadUrl'] . '/' . $galleryDir;
                }
                $config['webDir'] = $config['webDir'] . '/' . $galleryDir;
                $config['saveOriginal'] = false;
            }

            if (!isset($config['libraryDir'])) {
                if ($config['uploadDir']) {
                    $config['libraryDir'] = $config['uploadDir'];
                }
                else {
                    /**
                     * @deprecated since comur/image-bundle 2.0.3 due to security issue, to be removed in 2.1. Use uploadDir instead.
                     */
                    $config['libraryDir'] = $config['uploadUrl'];
                }
            }
            // if($config['saveOriginal']){
            //     $options['compound']=true;
            // }
            return $config;
        };
    }

    /**
     * Returns crop config normalizer. It can be used by compatible bundles to normalize parameters
     * @param $cropConfig
     * @return \Closure
     */
    public static function getCropConfigNormalizer($cropConfig)
    {
        return function (Options $options, $value) use ($cropConfig) {
            return array_merge($cropConfig, $value);
        };
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $uploadConfig = self::$uploadConfig;
        $cropConfig = self::$cropConfig;

        $resolver->setDefaults([
            'uploadConfig' => $uploadConfig,
            'cropConfig' => $cropConfig,
            // 'compound' => function(Options $options, $value) use($cropConfig){
            //     return $options['uploadConfig']['saveOriginal'] ? true : false;
            // },
            'inherit_data' => true,
            // 'property_path' => null,
            // 'data_class' => 'MVB\Bundle\MemberBundle\Entity\Member'
        ]);

        $isGallery = $this->isGallery;
        $galleryDir = $this->galleryDir;

        $resolver->setNormalizer(
            'uploadConfig',
            self::getUploadConfigNormalizer($uploadConfig, $isGallery, $galleryDir)
        );
        $resolver->setNormalizer(
            'cropConfig',
            self::getCropConfigNormalizer($cropConfig)
        );

    }

    /**
     * {@inheritdoc}
     */
    // public function finishView(FormView $view, FormInterface $form, array $options)
    // {
    //     var_dump($form->getParent()->get($options['uploadConfig']['saveOriginal']));exit;
    // }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $originalPhotoFieldId = null;

        $uploadConfig = $options['uploadConfig'];
        $cropConfig = $options['cropConfig'];

        $fieldImage = null;
        if (isset($cropConfig['thumbs']) && count($thumbs = $cropConfig['thumbs']) > 0) {
            foreach ($thumbs as $thumb) {
                if (isset($thumb['useAsFieldImage']) && $thumb['useAsFieldImage']) {
                    $fieldImage = $thumb;
                }
            }
        }

        $view->vars['options'] = [
            'uploadConfig' => $uploadConfig,
            'cropConfig' => $cropConfig,
            'fieldImage' => $fieldImage,
        ];
        $view->vars['attr'] = array_merge(
            isset($options['attr']) ? $options['attr'] : [],
            [
                'style' => 'opacity: 0;width: 0; max-width: 0; height: 0; max-height: 0;',
            ]
        );
    }
}
