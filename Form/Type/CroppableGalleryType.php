<?php

namespace Comur\ImageBundle\Form\Type;

use Symfony\Component\Form\AbstractType;

// use Symfony\Component\Form\FormBuilder;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CroppableGalleryType extends CroppableImageType
{
    protected $galleryDir = null;
    protected $thumbsDir = null;
    protected $isGallery = true;
    protected $galleryThumbSize = null;

    public function getBlockPrefix(): string
    {
        return 'comur_gallery';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder->add($builder->getName(), CollectionType::class, [
            // 'property_path' => $builder->getName(),
            // 'inherit_data' => true,
            'allow_add' => true,
            'allow_delete' => true,
            'entry_options' => [
                'attr' => array_merge(
                    isset($options['attr']) ? $options['attr'] : [],
                    [
                        'style' => 'opacity: 0;width: 0; max-width: 0; height: 0; max-height: 0;padding: 0; position: absolute;',
                    ]
                ),
            ],
        ]);
    }

    public function __construct($galleryDir, $thumbsDir, $galleryThumbSize)
    {
        $this->galleryDir = $galleryDir;
        $this->thumbsDir = $thumbsDir;
        $this->galleryThumbSize = $galleryThumbSize;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $uploadConfig = $options['uploadConfig'];
        $cropConfig = $options['cropConfig'];

        $uploadConfig['isGallery'] = true;

        $view->vars['options'] = [
            'uploadConfig' => $uploadConfig,
            'cropConfig' => $cropConfig,
            'galleryThumbSize' => $this->galleryThumbSize,
        ];
        // $view->vars['options']['attr'] = array('style' => 'opacity: 0;width: 0; max-width: 0; height: 0; max-height: 0;', 'multiple' => true);
        // $view->vars['attr'] = array('style' => 'opacity: 0;width: 0; max-width: 0; height: 0; max-height: 0;', 'multiple' => true);
    }
}
