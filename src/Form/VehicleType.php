<?php

namespace App\Form;

use App\Entity\Vehicle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VehicleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('marque', TextType::class, [
                'label' => 'Marque',
            ])
            ->add('immatriculation', TextType::class, [
                'label' => 'Immatriculation',
            ])
            ->add('prixJournalier', RangeType::class, [
                'label' => 'Prix journalier (€)',
                'attr' => [
                    'min' => 100,
                    'max' => 500,
                    'step' => 1,
                    'oninput' => 'document.getElementById("priceCounter").innerText = this.value'
                ],
                'help' => 'Le prix journalier doit être compris entre 100 et 500 €',
            ])
            ->add('disponible', CheckboxType::class, [
                'label' => 'Disponible',
                'required' => false,
            ])
            // Champ multiple pour sélectionner plusieurs images
            ->add('images', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'label' => 'Photo(s)',
                'attr' => [
                    'accept' => 'image/png, image/jpeg'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Vehicle::class,
        ]);
    }
}
