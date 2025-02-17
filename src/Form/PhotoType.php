<?php
namespace App\Form;

use App\Entity\Photo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhotoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FileType::class, [
                'label'    => 'Choisissez une image',
                'mapped'   => false,
                'required' => false,
                'attr'     => [
                    'accept' => 'image/png, image/jpeg'
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'help'  => 'Exemple : 1 pour la première photo, 2 pour la deuxième, etc.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Photo::class,
        ]);
    }
}
