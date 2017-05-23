<?php

namespace AdministrateurBundle\Controller;

use ConnexionBundle\Entity\Matiere;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AdministrateurBundle\Form\MatiereType;
use AdministrateurBundle\Form\PromotionType;
use AdministrateurBundle\Form\EtudiantType;
use AdministrateurBundle\Form\ConventionType;
use ConnexionBundle\Entity\Promotion;
use ConnexionBundle\Entity\User;
use ConnexionBundle\Entity\Convention;
use ConnexionBundle\Entity\Type;

class GestionPromotionController extends Controller
{
    public function listePromosAction()
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));

        $lesPromotions = $this->getDoctrine()->getRepository('ConnexionBundle:Promotion')->findAll();
        return $this->render('AdministrateurBundle:Default:liste_promos.html.twig', array(
            'lesPromotions' => $lesPromotions,
        ));
    }

    public function gestionPromoAction(Promotion $promotion = null)
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));

        // Si la promo est null, c'est qu'elle n'existe pas dans la BDD, on retoune à la page de gestion promo
        if(is_null($promotion)){
            return $this->redirect($this->generateUrl("liste_promotions"));
        }

        $les_etudiants = $promotion->getLesEtudiants();
        $les_matieres = $promotion->getLesMatieres();
        $les_conventions = $promotion->getLesConventions();
        $les_responsables = $promotion->getLesResponsables();

        if(is_null($les_responsables)){
            $les_responsables = $this->getDoctrine()->getRepository('ConnexionBundle:User')->findByRole(array('ROLE_RESPONSABLE'));
        }

        return $this->render('AdministrateurBundle:Default:gestion_promo.html.twig', array(
            'lesEtudiants' => $les_etudiants,
            'lesMatieres' => $les_matieres,
            'promotion' => $promotion,
            'lesResponsables' => $les_responsables,
            'lesConventions' => $les_conventions
        ));
    }

    /**
     * Fonction qui sert à ajouter une promotion
     * @param Request $request
     * @return ...
     */
    public function ajoutPromoAction(Request $request)
    {
        //Autorisation d'accès pour les admins uniquement
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));

        //$user contient l'utilisateur qui est connecté
        $user = $this->getUser();

        // On vérifie que la promo n'existe pas déjà (nom unique)
        //$promos = $this->getDoctrine()->getRepository('ConnexionBundle:Promotion')->find($promo);

        //$lesResponsables contient les utilisateurs qui ont le rôle Responsable

        $lesResponsables = $this->getDoctrine()->getRepository('ConnexionBundle:User')->findByRole(array('ROLE_RESPONSABLE'));

        //Objet promotion
        $promotion = new Promotion();

        //l'objet $form est créé avec en paramètre le FormType de l'objet qu'on veut créer/modifier.. et l'objet qui sera inséré dans la BDD à la fin
        $form = $this->createForm(new PromotionType($lesResponsables), $promotion);

        //Code qui s'exécute à la réception du formulaire
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) { //Si tous les champs sont valides
                $em = $this->getDoctrine()->getManager();
                $em->persist($promotion); //signale la création d'un nouvel objet $matiere
                $em->flush(); //Insertion dans la BDD

                return $this->redirect($this->generateUrl("liste_promotions")); // Redirection après l'ajout

            } else
                $this->addFlash('error', "Tous les champs doivent être complétés.");
        }

        //Affichage de la vue ajout_promotion.html.twig avec le formulaire en paramètre

        return $this->render('AdministrateurBundle:Default:ajout_promotion.html.twig', array(

            'form' => $form->createView(),

        ));

    }

    public function ajoutEtudiantAction(Request $request, Promotion $promotion = null)
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));
        $userManager = $this->container->get('fos_user.user_manager');
        $etudiant = $userManager->createUser();
        $form = $this->createForm(new EtudiantType(), $etudiant);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $username = substr($etudiant->getPrenom(), 0, 1) . "" . substr($etudiant->getNom(), 0, strlen($etudiant->getNom()));
                $role = $request->get("role");
                if ($role == "on") {
                    $etudiant->addRole("ROLE_ETUDIANT");
                } else {
                    $etudiant->addRole("ROLE_DELEGUE");
                    $etudiant->addRole("ROLE_ETUDIANT");
                }
                $etudiant->setPromotion($promotion);
                $etudiant->setUsername($username);
                $etudiant->setPlainPassword($username);
                $etudiant->setEnabled(true);
                $em = $this->getDoctrine()->getManager();
                $userManager->updateUser($etudiant, true);
                //$em->persist($etudiant);
                $em->flush();
                return $this->redirect($this->generateUrl("gerer_promotion"));
            } else
                $this->addFlash('error', "Tous les champs doivent être complétés.");
        }

        return $this->render('AdministrateurBundle:Default:ajout_etudiant.html.twig', array(
            'form' => $form->createView()
        ));
    }

    public function modificationEtudiantAction(Request $request, User $etudiant = null)
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));
        if(is_null($etudiant)){ //la matière n'existe pas
            return $this->redirectToRoute("gerer_promotions");
        }
        $userManager = $this->container->get('fos_user.user_manager');
        $form = $this->createForm(new EtudiantType(), $etudiant);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $username = substr($etudiant->getPrenom(), 0, 1) . "" . substr($etudiant->getNom(), 0, strlen($etudiant->getNom()));
                $role = $request->get("role");
                if ($role == "on") {
                    if ($etudiant->hasRole("ROLE_DELEGUE")){
                        $etudiant->removeRole("ROLE_DELEGUE");
                        $etudiant->addRole("ROLE_ETUDIANT");
                    }else {
                        $etudiant->addRole("ROLE_ETUDIANT");
                    }

                } else {
                    if(!$etudiant->hasRole("ROLE_DELEGUE")){
                        $etudiant->addRole("ROLE_DELEGUE");
                    }
                }
                $etudiant->setUsername($username);
                $etudiant->setPlainPassword($username);
                $etudiant->setEnabled(true);
                $em = $this->getDoctrine()->getManager();
                $userManager->updateUser($etudiant, true);
                $em->flush();

                return $this->redirect($this->generateUrl("gerer_promotion", array('id' => $etudiant->getPromotion()->getId())));
            } else
                $this->addFlash('error', "Tous les champs doivent être complétés.");
        }
        return $this->render('AdministrateurBundle:Default:modification_etudiant.html.twig', array(
            'form' => $form->createView(),
            'etudiant' => $etudiant
        ));
    }


    public function ajoutMatiereAction(Request $request, Promotion $promotion = null)
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));

        // Si la promo est null, c'est qu'elle n'existe pas dans la BDD, on retoune à la page de gestion promo
        if(is_null($promotion)){
            return $this->redirectToRoute("gerer_promotion", array('id' => $promotion->getId()));
        }

        $les_enseignants = $this->getDoctrine()->getRepository('ConnexionBundle:User')->findByRole(array('ROLE_ENSEIGNANT'));

        $matiere = new Matiere();
        $matiere->setPromo($promotion);

        $form = $this->createForm(new MatiereType($les_enseignants), $matiere);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($matiere);
                $em->flush();

                return $this->redirect($this->generateUrl("gerer_promotion", array('id' => $promotion->getId())));

            } else
                $this->addFlash('error', "Tous les champs doivent être complétés.");
        }

        return $this->render('AdministrateurBundle:Default:ajout_matiere.html.twig', array(
            'form' => $form->createView(),
            'matiere' => $matiere
        ));
    }

    public function modificationMatiereAction(Request $request, Matiere $matiere = null)
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));

        if(is_null($matiere)){ //la matière n'existe pas
            return $this->redirectToRoute("liste_promotions");
        }

        $les_enseignants = $this->getDoctrine()->getRepository('ConnexionBundle:User')->findByRole(array('ROLE_ENSEIGNANT'));
        $form = $this->createForm(new MatiereType($les_enseignants), $matiere);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->flush();

                return $this->redirectToRoute("gerer_promotion", array('id' => $matiere->getPromotion()->getId()));

            } else
                $this->addFlash('error', "Tous les champs doivent être complétés.");
        }

        return $this->render('AdministrateurBundle:Default:modification_matiere.html.twig', array(
            'form' => $form->createView(),
            'matiere' => $matiere
        ));
    }

    public function modificationPromotionAction(Request $request, Promotion $promotion = null)
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));

        if(is_null($promotion)){
            //return $this->redirect($this->generateUrl("gerer_promotion", array('id' => $matiere->getPromotion()->getId())));
            //return $this->redirectToRoute("gerer_promotion", array('id' => $matiere->getPromotion()->getId()));
        }

        $les_responsables = $this->getDoctrine()->getRepository('ConnexionBundle:User')->findByRole(array('ROLE_RESPONSABLE'));
        $form = $this->createForm(new PromotionType($les_responsables), $promotion);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->flush();

                return $this->redirectToRoute("liste_promotions");

            } else
                $this->addFlash('error', "Tous les champs doivent être complétés.");
        }

        return $this->render('AdministrateurBundle:Default:modification_promotion.html.twig', array(
            'form' => $form->createView(),
            'promotion' => $promotion
        ));
    }

    public function ajoutConventionAction(Request $request, Promotion $promotion = null)
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));

        // Si la promo est null, c'est qu'elle n'existe pas dans la BDD, on retoune à la page de gestion promo
        if (is_null($promotion)) {
            return $this->redirectToRoute("gerer_promotion", array('id' => $promotion->getId()));
        }

        $lesTypes = $this->getDoctrine()->getRepository('ConnexionBundle:Type')->findAll();

        $convention = new Convention();
        $convention->setPromotion($promotion);

        $form = $this->createForm(new ConventionType($lesTypes), $convention);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($convention);
                $em->flush();

                return $this->redirect($this->generateUrl("gerer_promotion", array('id' => $promotion->getId())));

            } else
                $this->addFlash('error', "Tous les champs doivent être complétés.");
        }

        return $this->render('AdministrateurBundle:Default:ajout_convention.html.twig', array(
            'form' => $form->createView(),
            'convention' => $convention
        ));
    }

    public function modificationConventionAction(Request $request, Convention $convention = null)
    {
        $this->denyAccessUnlessGranted(array('ROLE_ADMIN'));

        if(is_null($convention)){ //la convention n'existe pas
            return $this->redirectToRoute("liste_promotions");
        }

        $lesTypes = $this->getDoctrine()->getRepository('ConnexionBundle:Type')->findAll();
        $form = $this->createForm(new ConventionType($lesTypes), $convention);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->flush();

                return $this->redirectToRoute("gerer_promotion", array('id' => $convention->getPromotion()->getId()));

            } else
                $this->addFlash('error', "Tous les champs doivent être complétés.");
        }

        return $this->render('AdministrateurBundle:Default:modification_convention.html.twig', array(
            'form' => $form->createView(),
            'convention' => $convention
        ));
    }
}
