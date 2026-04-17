<?php

namespace App\Controller\backOffice;

use App\Entity\Asset;
use App\Entity\Utilisateur;
use App\Form\AssetType;
use App\Repository\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/asset', name: 'app_backoffice_asset_')]
class AssetController extends AbstractController
{

      #[Route('/', name: 'index', methods: ['GET'])]

    public function index(Request $request, AssetRepository $repo): Response
    {
        $query  = trim($request->query->get('q', ''));
        $assets = $query ? $repo->search($query) : $repo->findAll();
 
        return $this->render('asset/asset.html.twig', [
            'mode'   => 'index',
            'assets' => $assets,
            'q'      => $query,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, AssetRepository $repo): Response
    {
        $asset = new Asset();
        $form  = $this->createForm(AssetType::class, $asset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

         
            $existing = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);
            if ($existing) {
                $this->addFlash('danger', 'An asset with symbol "' . $asset->getSymbol() . '" already exists.');
                return $this->render('asset/asset.html.twig', [
                    'mode'  => 'form',
                    'asset' => $asset,
                    'form'  => $form->createView(),
                ]);
            }

           
            if ($asset->getStatus() === Asset::STATUS_INACTIVE) {
                $this->addFlash('danger', 'A new asset cannot be created with Disabled status.');
                return $this->render('asset/asset.html.twig', [
                    'mode'  => 'form',
                    'asset' => $asset,
                    'form'  => $form->createView(),
                ]);
            }

            $asset->setCreatedAt(new \DateTimeImmutable());
            $asset->setUpdatedAt(new \DateTimeImmutable());
            $utilisateur = $em->getReference(Utilisateur::class,1);
            $asset->setUtilisateur($utilisateur);
            $em->persist($asset);
            $em->flush();
            $this->addFlash('success', 'Asset "' . $asset->getName() . '" added successfully.');
            return $this->redirectToRoute('app_backoffice_asset_index');
        }

        return $this->render('asset/asset.html.twig', [
            'mode'  => 'form',
            'asset' => $asset,
            'form'  => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Asset $asset, EntityManagerInterface $em, AssetRepository $repo): Response
    {
        
        if ($asset->getStatus() === Asset::STATUS_INACTIVE) {
            $this->addFlash('danger', 'Disabled assets cannot be edited.');
            return $this->redirectToRoute('app_backoffice_asset_index');
        }

        $form = $this->createForm(AssetType::class, $asset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

          
            $existing = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);
            if ($existing && $existing->getId() !== $asset->getId()) {
                $this->addFlash('danger', 'An asset with symbol "' . $asset->getSymbol() . '" already exists.');
                return $this->render('asset/asset.html.twig', [
                    'mode'  => 'form',
                    'asset' => $asset,
                    'form'  => $form->createView(),
                ]);
            }

            $asset->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Asset "' . $asset->getName() . '" updated successfully.');
            return $this->redirectToRoute('app_backoffice_asset_index');
        }

        return $this->render('asset/asset.html.twig', [
            'mode'  => 'form',
            'asset' => $asset,
            'form'  => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Asset $asset, EntityManagerInterface $em): Response
    {
       
        if ($asset->getStatus() === Asset::STATUS_ACTIVE) {
            $this->addFlash('danger', 'Active assets cannot be deleted. Disable it first.');
            return $this->redirectToRoute('app_backoffice_asset_index');
        }

        if ($this->isCsrfTokenValid('delete' . $asset->getId(), $request->request->get('_token'))) {
            $em->remove($asset);
            $em->flush();
            $this->addFlash('warning', 'Asset "' . $asset->getName() . '" deleted successfully.');
        }

        return $this->redirectToRoute('app_backoffice_asset_index');
    }
}