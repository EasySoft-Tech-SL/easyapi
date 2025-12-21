<?php
/**
 * =============================================================================
 * EJEMPLO 09: INTEGRACIÓN CON DOLIBARR
 * =============================================================================
 *
 * Demuestra cómo usar las clases y objetos nativos de Dolibarr.
 *
 * MÉTODOS Y PROPIEDADES:
 *   - $this->loadClass('/path/class.php')  → Cargar clase de Dolibarr
 *   - $this->getUser($req)                 → Obtener usuario autenticado
 *   - $this->conf                          → Configuración global
 *   - $this->db                            → Conexión a BD
 *   - $this->langs                         → Traducciones
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class DolibarrIntegrationResource extends EasyApiResource
{
    protected $description = 'Integración con clases nativas de Dolibarr';

    protected function registerRoutes(): void
    {
        // =====================================================================
        // Cargar y usar clase Societe (Terceros)
        // =====================================================================
        $this->get('/tercero/{id}', 'Ver tercero', function ($req, $res, $args) {
            $id = (int) $args['id'];

            // Cargar la clase de Dolibarr
            $this->loadClass('/societe/class/societe.class.php');

            // Crear instancia y cargar datos
            $societe = new \Societe($this->db);
            $result = $societe->fetch($id);

            if ($result <= 0) {
                return $this->notFound($res, "Tercero #$id no encontrado");
            }

            return $this->ok($res, array(
                'id' => $societe->id,
                'nom' => $societe->nom,
                'name_alias' => $societe->name_alias,
                'email' => $societe->email,
                'phone' => $societe->phone,
                'address' => $societe->address,
                'zip' => $societe->zip,
                'town' => $societe->town,
                'country' => $societe->country,
                'client' => (bool) $societe->client,
                'fournisseur' => (bool) $societe->fournisseur,
                'code_client' => $societe->code_client,
                'siret' => $societe->idprof1,
                'tva_intra' => $societe->tva_intra
            ));
        })
        ->pathParams(array(
            'id' => array('type' => 'integer', 'description' => 'ID del tercero')
        ))
        ->tags('Dolibarr - Terceros')
        ->describe('Usa la clase Societe de Dolibarr para obtener un tercero.');


        // =====================================================================
        // Crear tercero con clase Dolibarr
        // =====================================================================
        $this->post('/tercero', 'Crear tercero', function ($req, $res) {
            $schema = array(
                'required' => array('nom'),
                'properties' => array(
                    'nom' => array('type' => 'string', 'minLength' => 2),
                    'email' => array('type' => 'string', 'format' => 'email'),
                    'phone' => array('type' => 'string'),
                    'address' => array('type' => 'string'),
                    'zip' => array('type' => 'string'),
                    'town' => array('type' => 'string'),
                    'client' => array('type' => 'integer'),
                    'fournisseur' => array('type' => 'integer')
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            $data = $validation['data'];
            $user = $this->getUser($req);

            $this->loadClass('/societe/class/societe.class.php');

            $societe = new \Societe($this->db);
            $societe->nom = $data['nom'];
            $societe->email = isset($data['email']) ? $data['email'] : '';
            $societe->phone = isset($data['phone']) ? $data['phone'] : '';
            $societe->address = isset($data['address']) ? $data['address'] : '';
            $societe->zip = isset($data['zip']) ? $data['zip'] : '';
            $societe->town = isset($data['town']) ? $data['town'] : '';
            $societe->client = isset($data['client']) ? $data['client'] : 1;
            $societe->fournisseur = isset($data['fournisseur']) ? $data['fournisseur'] : 0;
            $societe->country_id = 4; // España

            $result = $societe->create($user);

            if ($result < 0) {
                return $this->error($res, 'Error al crear tercero: ' . $societe->error);
            }

            return $this->created($res, array(
                'id' => $result,
                'nom' => $societe->nom,
                'code_client' => $societe->code_client,
                'message' => 'Tercero creado correctamente'
            ));
        })
        ->body(array(
            'required' => array('nom'),
            'properties' => array(
                'nom' => array('type' => 'string', 'description' => 'Nombre/Razón social'),
                'email' => array('type' => 'string', 'description' => 'Email'),
                'phone' => array('type' => 'string', 'description' => 'Teléfono'),
                'address' => array('type' => 'string', 'description' => 'Dirección'),
                'zip' => array('type' => 'string', 'description' => 'Código postal'),
                'town' => array('type' => 'string', 'description' => 'Ciudad'),
                'client' => array('type' => 'integer', 'description' => '1=Cliente, 0=No cliente'),
                'fournisseur' => array('type' => 'integer', 'description' => '1=Proveedor, 0=No proveedor')
            )
        ))
        ->requirePermission('societe->creer')
        ->tags('Dolibarr - Terceros')
        ->describe('Crea un nuevo tercero usando la clase Societe de Dolibarr.');


        // =====================================================================
        // Usar clase Facture (Facturas)
        // =====================================================================
        $this->get('/factura/{id}', 'Ver factura', function ($req, $res, $args) {
            $id = (int) $args['id'];

            $this->loadClass('/compta/facture/class/facture.class.php');

            $facture = new \Facture($this->db);
            $result = $facture->fetch($id);

            if ($result <= 0) {
                return $this->notFound($res, "Factura #$id no encontrada");
            }

            // Cargar las líneas
            $facture->fetch_lines();

            $lineas = array();
            foreach ($facture->lines as $line) {
                $lineas[] = array(
                    'id' => $line->id,
                    'description' => $line->desc,
                    'qty' => $line->qty,
                    'subprice' => $line->subprice,
                    'total_ht' => $line->total_ht,
                    'total_ttc' => $line->total_ttc,
                    'tva_tx' => $line->tva_tx
                );
            }

            return $this->ok($res, array(
                'id' => $facture->id,
                'ref' => $facture->ref,
                'socid' => $facture->socid,
                'date' => date('Y-m-d', $facture->date),
                'date_lim_reglement' => $facture->date_lim_reglement ? date('Y-m-d', $facture->date_lim_reglement) : null,
                'total_ht' => $facture->total_ht,
                'total_tva' => $facture->total_tva,
                'total_ttc' => $facture->total_ttc,
                'statut' => $facture->statut,
                'statut_label' => $facture->getLibStatut(1),
                'paye' => (bool) $facture->paye,
                'lineas' => $lineas
            ));
        })
        ->pathParams(array(
            'id' => array('type' => 'integer', 'description' => 'ID de la factura')
        ))
        ->requirePermission('facture->lire')
        ->tags('Dolibarr - Facturas')
        ->describe('Obtiene una factura con sus líneas usando la clase Facture.');


        // =====================================================================
        // Información del usuario autenticado
        // =====================================================================
        $this->get('/mi-perfil', 'Mi perfil', function ($req, $res) {
            $user = $this->getUser($req);

            return $this->ok($res, array(
                'id' => $user->id,
                'login' => $user->login,
                'lastname' => $user->lastname,
                'firstname' => $user->firstname,
                'email' => $user->email,
                'admin' => (bool) $user->admin,
                'entity' => $user->entity,
                'lang' => $user->lang,
                'photo' => $user->photo,
                'datec' => $user->datec,
                'datelastlogin' => $user->datelastlogin
            ));
        })
        ->tags('Dolibarr - Usuario')
        ->describe('Obtiene información del usuario autenticado.');


        // =====================================================================
        // Configuración global de Dolibarr
        // =====================================================================
        $this->get('/configuracion', 'Configuración', function ($req, $res) {
            return $this->ok($res, array(
                'empresa' => array(
                    'nombre' => $this->conf->global->MAIN_INFO_SOCIETE_NOM,
                    'direccion' => $this->conf->global->MAIN_INFO_SOCIETE_ADDRESS,
                    'cp' => $this->conf->global->MAIN_INFO_SOCIETE_ZIP,
                    'ciudad' => $this->conf->global->MAIN_INFO_SOCIETE_TOWN,
                    'pais' => $this->conf->global->MAIN_INFO_SOCIETE_COUNTRY,
                    'email' => $this->conf->global->MAIN_INFO_SOCIETE_MAIL,
                    'web' => $this->conf->global->MAIN_INFO_SOCIETE_WEB
                ),
                'sistema' => array(
                    'version' => DOL_VERSION,
                    'entity' => $this->conf->entity,
                    'moneda' => $this->conf->currency,
                    'idioma' => $this->conf->global->MAIN_LANG_DEFAULT,
                    'tema' => $this->conf->global->MAIN_THEME
                ),
                'modulos_activos' => array(
                    'societe' => !empty($this->conf->societe->enabled),
                    'facture' => !empty($this->conf->facture->enabled),
                    'commande' => !empty($this->conf->commande->enabled),
                    'propal' => !empty($this->conf->propal->enabled),
                    'product' => !empty($this->conf->product->enabled),
                    'stock' => !empty($this->conf->stock->enabled)
                )
            ));
        })
        ->tags('Dolibarr - Sistema')
        ->describe('Obtiene configuración general de Dolibarr.');


        // =====================================================================
        // Usar traducciones
        // =====================================================================
        $this->get('/traducciones', 'Traducciones', function ($req, $res) {
            // Cargar archivo de idioma
            $this->langs->load('main');
            $this->langs->load('companies');
            $this->langs->load('bills');

            return $this->ok($res, array(
                'idioma_actual' => $this->langs->defaultlang,
                'traducciones' => array(
                    'Yes' => $this->langs->trans('Yes'),
                    'No' => $this->langs->trans('No'),
                    'Company' => $this->langs->trans('Company'),
                    'Customer' => $this->langs->trans('Customer'),
                    'Supplier' => $this->langs->trans('Supplier'),
                    'Invoice' => $this->langs->trans('Invoice'),
                    'Amount' => $this->langs->trans('Amount'),
                    'Date' => $this->langs->trans('Date'),
                    'Status' => $this->langs->trans('Status')
                )
            ));
        })
        ->tags('Dolibarr - Sistema')
        ->describe('Ejemplo de uso del sistema de traducciones.');
    }
}
