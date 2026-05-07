<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;



class UserController extends Controller
{
    // public $company_ids = [
    //     94,
    //     214,
    //     254,
    //     276,
    //     279,
    //     280,
    //     282,
    //     366,
    //     288,
    //     292,
    //     293,
    //     305,
    //     306,
    //     307,
    //     308,
    //     309,
    //     310,
    //     311,
    //     312,
    //     314,
    //     315,
    //     316,
    //     322,
    //     333,
    //     334,
    //     526,
    //     527,
    //     537,
    //     538,
    //     521,
    //     523,
    //     549,
    //     550,
    //     551,
    //     553,
    //     554,
    //     555,
    //     557,
    //     560,
    //     561,
    //     563,
    //     565,
    //     566,
    //     567,
    //     569,
    //     571,
    //     572,
    //     578,
    //     579,
    //     580,
    //     582,
    //     583,
    //     474,
    //     475,
    //     505,
    //     513,
    //     514,
    //     515,
    //     470,
    //     471,
    //     473,
    //     518,
    //     358,
    //     366,
    //     398,
    //     411,
    //     431,
    //     432,
    //     462,
    //     466,
    //     581,
    //     337
    // ];
    public $company_ids = [537, 474, 280, 475, 583, 214, 526, 549, 276, 279, 515, 521, 551, 553, 557, 560, 567, 579, 582, 584];
    public $company_id  = null;
    public $new_company = null;

    public function migrateCompany()
    {
        $company = DB::table('company as c')->join('companyprofile as cp', 'cp.company_id', '=', 'c.id')->where('c.id', $this->company_id)
            ->select('c.*', 'c.createdAt as company_create_at', 'c.updatedAt as company_updated_at', 'cp.*')
            ->first();
        if (!$company) {
            abort(400, 'Company not found');
        }

        $new_c = Str::uuid();
        $saved = DB::connection('mysql2')->table('companies')->insert([
            'id' => $new_c,
            'old_company_id' => $this->company_id,
            'name' => $company->company_name,
            'slug' => $company->company_slug,
            'telephone' => $company->phone,
            'address' => $company->company_address,
            'state' => $company->state ? json_decode($company->state)->state : null,
            'country' => $company->country,
            'type' => 'Private limited company',
            'domain_name' => $company->domain_name,
            'rc_number' => $company->registration_number,
            'bvn_verified_at' => $company->verification_status || $company->bvn_verified ? now() : null,
            'created_at' => $company->company_create_at == '0000-00-00 00:00:00' ? ($company->company_updated_at ? $company->company_updated_at : now()) : $company->company_create_at,
            'updated_at' => $company->company_updated_at == '0000-00-00 00:00:00' ? now() : $company->company_updated_at,
            // 'referrer_id' => $company->company_slug,
            'default_currency_id' => 1,
            // 'billing_budget_category_id' => $company->company_slug,
        ]);

        if ($company->account_number) {
            DB::connection('mysql2')->table('companies_accounts')->insert([
                'company_id' => $new_c,
                'account_name' => $company->company_name,
                'account_number' => $company->account_number,
                'bank_name' => 'Wema Bank',
                'bank_code' => '035',
                'account_balance' => 0,
            ]);
        }

        if ($company->providus) {
            DB::connection('mysql2')->table('companies_accounts')->insert([
                'company_id' => $new_c,
                'account_name' => $company->company_name,
                'account_number' => $company->providus,
                'bank_name' => 'Providus Bank',
                'bank_code' => '035',
                'account_balance' => 0,
            ]);
        }


        //get and save preferencies
        $preferences = DB::connection('mysql2')->table('preferences')->where('is_company', true)->get();
        foreach ($preferences as $value) {
            DB::connection('mysql2')->table('companies_preferences')->insert([
                'companiesId' => $new_c,
                'preferencesId' => $value->id,
                'status' => $value->value == 'DISABLE_OTP_FOR_OFFLINE_REQUEST' ? true : false,
                'visible' => false
            ]);
        }
        //save currency
        DB::connection('mysql2')->table('companies_currencies')->insert([
            'companiesId' => $new_c,
            'currenciesId' => 1,
        ]);

        // migrate roles and pernissions
        $permissions = DB::connection('mysql2')->table('permissions')->get();
        $default_roles = [
            [
                'name' => 'Approver',
                'description' => 'Has the authority to review and approve requests, ensuring compliance and alignment with organizational policies and goals.',
                'permission' => [
                    'CREATE_ONLINE_REQUESTS',
                    'CREATE_OFFLINE_REQUESTS',
                    'CREATE_PURCHASE_REQUESTS',
                    'ALLOW_OFFLINE_APPROVAL',
                    'ALLOW_ONLINE_APPROVAL',
                    'ALLOW_PURCHASE_APPROVAL',
                    'UPDATE_PAYMENT_REQUEST',
                    'VIEW_REPORT_STATEMENT',
                    'VIEW_REPORT_BUDGET_STATUS',
                    'VIEW_REPORT_EXPENSE_CATEGORY',
                ],
            ],
            [
                'name' => 'Requester',
                'description' => 'Initiates requests for various actions or resources within the system, such as purchases, access, or approvals.',
                'permission' => [
                    'CREATE_ONLINE_REQUESTS',
                    'CREATE_OFFLINE_REQUESTS',
                    'CREATE_PURCHASE_REQUESTS',
                ],
            ],
            [
                'name' => 'Tech',
                'description' => 'IT personnel.',
                'permission' => [
                    'CREATE_PAYEE',
                    'ARCHIVE_PAYEE',
                    'VIEW_PAYEES_PAGE',
                    'VIEW_BILLING_PAGE',
                    'VIEW_EXPENSE_CATEGORY_PAGE',
                    'CREATE_ONLINE_REQUESTS',
                    'CREATE_OFFLINE_REQUESTS',
                    'CREATE_PURCHASE_REQUESTS',
                    'CREATE_EXPENSE_CATEGORY',
                    'DELETE_EXPENSE_CATEGORY',
                    'EDIT_EXPENSE_CATEGORY',
                    'UPDATE_TEAM',
                    'CREATE_TEAM',
                    'EXPORT_TEAM',
                    'CREATE_ROLES',
                    'EDIT_ROLES',
                ],
            ],
            [
                'name' => 'Finance',
                'description' => 'Manages financial operations within the system, including budgeting, accounting, and financial reporting.',
                'permission' => [
                    'CREATE_ONLINE_REQUESTS',
                    'CREATE_OFFLINE_REQUESTS',
                    'CREATE_PURCHASE_REQUESTS',
                    'CREATE_PAYEE',
                    'ARCHIVE_PAYEE',
                    'VIEW_PAYEES_PAGE',
                    'VIEW_WALLET_PAGE',
                    'VIEW_WALLET_HISTORY',
                    'EXPORT_WALLET_HISTORY',
                    'VIEW_TRANSACTIONS',
                    'EXPORT_TRANSACTIONS',
                    'VIEW_COMPANY_BALANCE',
                    'VIEW_BILLING_PAGE',
                    'VIEW_EXPENSE_CATEGORY_PAGE',
                    'CREATE_EXPENSE_CATEGORY',
                    'DELETE_EXPENSE_CATEGORY',
                    'EDIT_EXPENSE_CATEGORY',
                    'CREATE_APPROVAL_CIRCLE',
                    'FINAL_FINANCE_DISBURSEMENT',
                    'VIEW_REPORT_STATEMENT',
                    'VIEW_REPORT_BUDGET_STATUS',
                    'VIEW_REPORT_EXPENSE_CATEGORY',
                    'EDIT_APPROVAL_CIRCLE',
                    'DELETE_APPROVAL_CIRCLE',
                    'UPDATE_PAYMENT_REQUEST',
                    'REVIEWER_MODE',
                    'VIEW_ALL_OFFLINE_PAYMENT_REQUESTS',
                    'VIEW_ALL_ONLINE_PAYMENT_REQUESTS',
                    'VIEW_ALL_PURCHASE_REQUESTS'
                ],
            ],
            [
                'name' => 'Admin',
                'description' => 'Holds administrative privileges and responsibilities, managing user accounts, configurations, and system settings.',
                'permission' => [
                    'CREATE_ONLINE_REQUESTS',
                    'CREATE_OFFLINE_REQUESTS',
                    'CREATE_PURCHASE_REQUESTS',
                    'IS_ADMIN',
                    'VIEW_ALL_OFFLINE_PAYMENT_REQUESTS',
                    'VIEW_ALL_ONLINE_PAYMENT_REQUESTS',
                    'VIEW_ALL_PURCHASE_REQUESTS',
                    'CREATE_PAYEE',
                    'ARCHIVE_PAYEE',
                    'VIEW_PAYEES_PAGE',
                    'VIEW_WALLET_PAGE',
                    'VIEW_WALLET_HISTORY',
                    'EXPORT_WALLET_HISTORY',
                    'VIEW_TRANSACTIONS',
                    'VIEW_COMPANY_ACCOUNT',
                    'EXPORT_TRANSACTIONS',
                    'VIEW_REPORT_STATEMENT',
                    'VIEW_REPORT_BUDGET_STATUS',
                    'VIEW_REPORT_EXPENSE_CATEGORY',
                    'EDIT_APPROVAL_CIRCLE',
                    'DELETE_APPROVAL_CIRCLE',
                    'VIEW_COMPANY_BALANCE',
                    'VIEW_BILLING_PAGE',
                    'VIEW_EXPENSE_CATEGORY_PAGE',
                    'CREATE_EXPENSE_CATEGORY',
                    'DELETE_EXPENSE_CATEGORY',
                    'EDIT_EXPENSE_CATEGORY',
                    'CREATE_TEAM',
                    'UPDATE_TEAM',
                    'VIEW_TEAMS_PAGE',
                    'COMPANY_KYC',

                ],
            ],
            [
                'name' => 'Endorser',
                'description' => 'Provides formal support or endorsement for requests or actions within the system, lending credibility and approval.',
                'permission' => [
                    'CREATE_ONLINE_REQUESTS',
                    'CREATE_OFFLINE_REQUESTS',
                    'CREATE_PURCHASE_REQUESTS',
                    'ALLOW_OFFLINE_ENDORSEMENT',
                    'ALLOW_ONLINE_ENDORSEMENT',
                    'ALLOW_PURCHASE_ENDORSEMENT',
                ],
            ],
            [
                'name' => 'Supplier',
                'description' => 'Provides goods, services, or resources to the system, often through contractual agreements or partnerships.',
                'permission' => [
                    'CREATE_ONLINE_REQUESTS',
                    'CREATE_OFFLINE_REQUESTS',
                    'CREATE_PURCHASE_REQUESTS',
                    'IS_SUPPLIER',
                ],
            ],
        ];
        foreach ($default_roles as $role) {
            $rId = DB::connection('mysql2')->table('roles')->insertGetId([
                'company_id' => $new_c,
                'name' => $role['name'],
                'description' => $role['description']
            ]);
            foreach ($role['permission'] as $per) {
                $firstid = '';
                foreach ($permissions as $permission) {
                    if ($permission->name == $per) {
                        $firstid = $permission->id;
                        break;
                    }
                }
                DB::connection('mysql2')->table('roles_permissions')->insert([
                    'rolesId' => $rId,
                    'permissionsId' => $firstid,
                ]);
            }
        }

        //save default expense category
        DB::connection('mysql2')->table('budget_categories')->insert([
            "name" => 'Unclassified',
            "description" => "This is an unclassified category created by default for all registered organizations",
            "budget" => 0,
            "budget_type" => 'monthly',
            "budget_manager" => 'organization',
            "has_subcategory" => 0,
            "can_overspend" => 1,
            "currency_id" => 1,
            "company_id" => $new_c,

        ]);

        //save default approver circle
        $ac = [
            [
                "name" => "Limitless",
                "description" => "This approval circle has no limits.",
                "limit" => 9999999999999,
                "type" => "ONLINE_PAYMENT",
                "currency_id" => 1,
                "company_id" => $new_c,
            ],
            [
                "name" => "Unassigned",
                "description" => "These are Approvers for whom an approval limit has not been set by being placed in an Approval Circle. Please assign them to a Circle so that they can start approving",
                "limit" => 0,
                "currency_id" => 1,
                "company_id" => $new_c,
                "type" => null,
            ],
            [
                "name" => "Offline",
                "description" => "",
                "limit" => 9999999999999,
                "type" => "OFFLINE_PAYMENT",
                "currency_id" => 1,
                "company_id" => $new_c,
            ],
        ];
        DB::connection('mysql2')->table('approval_circles')->insert($ac);

        return $new_c;
    }

    public function moveUsers()
    {
        $users = DB::table('users as u')
            ->leftJoin('userprofile as p', 'p.user_id', '=', 'u.id')
            ->select(
                'u.id as user_id',
                'u.email as email',
                'u.password as password',
                'u.role_id',
                'u.email_verified',
                'u.createdAt as createdAt',
                'u.updatedAt as updatedAt',
                'u.deletedAt as deletedAt',
                'u.deletedBy as deletedBy',
                'u.offlineApprovalCircle as offlineApprover',
                'u.onlineApprovalCircle as onlineApprover',
                'p.first_name',
                'p.last_name',
                'p.department',
                'p.personal_address',
                'p.phone',
                'p.createdAt as profileCreatedAt'
            )
            ->where('u.company_id', $this->company_id)->get();
        $com = DB::connection('mysql2')->table('companies')->where('id', $this->new_company)->first();
        if (!$com) {
            return 'New company not found';
        }
        foreach ($users as $user) {
            $find = DB::connection('mysql2')->table('users')->where('email', $user->email)->first();
            if ($find) {
                $staffId = DB::connection('mysql2')->table('staffs')->insertGetId([
                    'status' => 'active',
                    'user_id' => $find->id,
                    'old_user_id' => $user->user_id,
                    'company_id' => $this->new_company,
                    'created_at' => !$user->profileCreatedAt || $user->profileCreatedAt == '0000-00-00 00:00:00'  ? now() : $user->profileCreatedAt,
                    'deleted_at' => $user->deletedAt ? $user->deletedAt : null,
                    'status' => $user->deletedAt ? 'archived' : 'active'
                ]);
            } else {
                $userId = DB::connection('mysql2')->table('users')->insertGetId([
                    'old_user_id' => $user->user_id,
                    'email' => $user->email,
                    'password' => $user->password,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone' => $user->phone,
                    'address' => $user->personal_address,
                    'email_verified_at' => $user->email_verified == 1 ? now() : null,
                    'created_at' => !$user->createdAt || $user->createdAt == '0000-00-00 00:00:00' ? ($user->updatedAt ? $user->updatedAt : now()) : $user->createdAt,
                    'updated_at' => !$user->updatedAt || $user->updatedAt == '0000-00-00 00:00:00' ? now() : $user->updatedAt,
                    'deleted_at' => $user->deletedAt ? $user->deletedAt : null,
                ]);
                $staffId = DB::connection('mysql2')->table('staffs')->insertGetId([
                    'old_user_id' => $user->user_id,
                    'status' => 'active',
                    'user_id' => $userId,
                    'company_id' => $this->new_company,
                    'deleted_at' => $user->deletedAt ? $user->deletedAt : null,
                ]);
            }
            $role = str_replace(" ", '', $user->role_id);
            $role = explode(",", $role);
            $roles = DB::connection('mysql2')->table('roles')->where('company_id', $this->new_company)->get();
            $data = [];
            foreach ($role as $rl) {
                if ($rl == 4) {
                    foreach ($roles as $op) {
                        if ($op->name == 'Requester') {
                            array_push($data, ['staffsId' => $staffId, 'rolesId' => $op->id]);
                            break;
                        }
                    }
                } elseif ($rl == 14) {
                    foreach ($roles as $op) {
                        if ($op->name == 'Approver') {
                            array_push($data, ['staffsId' => $staffId, 'rolesId' => $op->id]);
                            break;
                        }
                    }
                } elseif ($rl == 24 || $rl == 64 || $rl == 74) {
                    foreach ($roles as $op) {
                        if ($op->name == 'Finance') {
                            array_push($data, ['staffsId' => $staffId, 'rolesId' => $op->id]);
                            break;
                        }
                    }
                } elseif ($rl == 34) {
                    foreach ($roles as $op) {
                        if ($op->name == 'Tech') {
                            array_push($data, ['staffsId' => $staffId, 'rolesId' => $op->id]);
                            break;
                        }
                    }
                } elseif ($rl == 44) {
                    foreach ($roles as $op) {
                        if ($op->name == 'Admin') {
                            array_push($data, ['staffsId' => $staffId, 'rolesId' => $op->id]);
                            break;
                        }
                    }
                } elseif ($rl == 54) {
                    foreach ($roles as $op) {
                        if ($op->name == 'Endorser') {
                            array_push($data, ['staffsId' => $staffId, 'rolesId' => $op->id]);
                            break;
                        }
                    }
                } elseif ($rl == 84) {
                    foreach ($roles as $op) {
                        if ($op->name == 'Supplier') {
                            array_push($data, ['staffsId' => $staffId, 'rolesId' => $op->id]);
                            break;
                        }
                    }
                }
            }
            DB::connection('mysql2')->table('staffs_roles')->insert($data);

            //save to
            $appc = DB::connection('mysql2')->table('approval_circles')->where('company_id', $this->new_company)->get();
            Log::info('Approval circles', (array)$appc);
            $ac_data = [];
            if ($user->onlineApprover == 4) {
                $onlineApprover = $appc->firstWhere('name', 'Limitless');
                $save = [
                    'approvalCirclesId' => $onlineApprover->id,
                    'staffsId' => $staffId
                ];
                array_push($ac_data, $save);
            }

            if ($user->offlineApprover == 4) {
                $offlineApprover = $appc->firstWhere('name', 'Offline');
                $save = [
                    'approvalCirclesId' => $offlineApprover->id,
                    'staffsId' => $staffId
                ];
                array_push($ac_data, $save);
            }

            if ($user->offlineApprover == 24) {
                $offlineApprover = $appc->firstWhere('name', 'Unassigned');
                $save = [
                    'approvalCirclesId' => $offlineApprover->id,
                    'staffsId' => $staffId
                ];
                array_push($ac_data, $save);
            }

            DB::connection('mysql2')->table('approval_circle_approvers')->insert($ac_data);
        }

        return "$this->new_company done";
    }

    public function migratePayee()
    {
        $payees = DB::table('suppliers')->where('company_id', $this->company_id)->get();
        $com = DB::connection('mysql2')->table('companies')->where('id', $this->new_company)->first();
        if (!$com) {
            return 'New Company not found';
        }

        $mass = [];
        foreach ($payees as $payee) {
            $created_by = $this->findUserWithOldId($payee->created_by); //DB::connection('mysql2')->table('staffs')->where('old_user_id', $payee->created_by)->first();
            $mass[] = [
                'name' => $payee->name,
                'company_id' => $this->new_company,
                'has_supplier_access' => false,
                'bank_country' => 'Nigeria',
                'currency' => 'NGN',
                'bank_name' => $payee->bank_name,
                'account_name' => $payee->account_name,
                'account_number' => $payee->account_number,
                'email' => $payee->email,
                'phone' => $payee->phone,
                'country' => 'Nigeria',
                'contact_person' => $payee->contact_name,
                'deleted_at' => $payee->is_deleted == 1 ? now() : null,
                'bank_code' => $payee->bank_code,
                'type' => $payee->type == 1 ? 'employee' : ($payee->type == 0 ? 'supplier' : 'others'),
                'created_by' => $created_by ?  $created_by->id : null,
                'old_payee_id' => $payee->id,
            ];
        }
        $ab = DB::connection('mysql2')->table('payees')->insert($mass);

        return "$this->new_company payee done";
    }

    public function migrateGroups()
    {
        $groups = DB::table('groups')->where('company_id', $this->company_id)->get();
        $com = DB::connection('mysql2')->table('companies')->where('id', $this->new_company)->first();
        if (!$com) {
            return 'New Company not found';
        }

        foreach ($groups as $gp) {
            $created_by = DB::connection('mysql2')->table('staffs')->where('old_user_id', $gp->created_by)->first();
            $deleted_by = DB::connection('mysql2')->table('staffs')->where('old_user_id', $gp->deleted_by)->first();

            $staff = DB::table('groups_staffs')->where('group_id', $gp->id)->get();
            $gid = DB::connection('mysql2')->table('groups')->insertGetId([
                'title' => $gp->title,
                'company_id' => $this->new_company,
                'description' => $gp->description,
                'deleted_at' => $gp->deleted_at,
                'created_at' => $gp->createdAt,
                'updated_at' => $gp->updatedAt,
                'created_by' => $created_by ?  $created_by->id : null,
                'deleted_by' => $deleted_by ?  $deleted_by->id : null,
                'old_group_id' => $gp->id,
            ]);
            foreach ($staff as $st) {
                $staffs = $this->findUserWithOldId($st->staff); //DB::connection('mysql2')->table('staffs')->where('old_user_id', $st->staff_id)->where('company_id', $this->new_company)->first();
                if ($staffs) {
                    DB::connection('mysql2')->table('staffs_groups')->insert([
                        'groupsId' => $gid,
                        'staffsId' => $staffs->id,
                    ]);
                }
            }
        }
        return "$this->new_company groups done";
    }

    public function moveApprovalCircle()
    {
        // Get approval circles
        $approval_circles = DB::table('approval_circle')
            ->where('companyId', $this->company_id)
            ->get();


        $com = DB::connection('mysql2')->table('companies')->where('id', $this->new_company)->first();
        if (!$com) {
            return 'New company not found';
        }

        $setting = DB::table('settings')
            ->where('companyId', $this->company_id)
            ->latest('id')->first();

        $pref = json_decode($setting->preferences);

        if ($pref->approvalCircle) {
            $preferences = DB::connection('mysql2')->table('preferences')->where('value', 'ENABLE_APPROVAL_CIRCLE')->first();
            if ($preferences) {
                DB::connection('mysql2')->table('companies_preferences')->where('companiesId', $this->new_company)->where('preferencesId', $preferences->id)->update([
                    'status' => true,
                    'visible' => true
                ]);
            }
        }

        // Preload all staffs ONCE instead of querying inside the loop
        // Fetch only staff with old_user_id matching any approver->id
        $allApproverIds = [];

        foreach ($approval_circles as $ac) {
            $decoded = json_decode($ac->approvers);
            foreach ($decoded as $a) {
                $allApproverIds[] = $a->id;
            }
        }

        $staffs = DB::connection('mysql2')
            ->table('staffs')
            ->whereIn('old_user_id', $allApproverIds)
            ->get()
            ->keyBy('old_user_id'); // faster lookup

        // Insert approval circles & store mapping of old → new circle id
        $circleIdMap = [];

        foreach ($approval_circles as $ac) {
            $save = [
                "name" => $ac->title,
                "description" => $ac->description,
                "limit" => $ac->limit * 100,
                "currency_id" => 1,
                "company_id" => $this->new_company,
                "type" => 'ONLINE_PAYMENT',
                "deleted_at" => $ac->isDeleted ? now() : null,
                "created_at" => $ac->createdAt,
                "updated_at" => $ac->updatedAt,
            ];

            $newId = DB::connection('mysql2')
                ->table('approval_circles')
                ->insertGetId($save);

            $circleIdMap[$ac->id] = [
                "newId" => $newId,
                "approvers" => json_decode($ac->approvers)
            ];
        }

        // Build all approver mappings FIRST
        $approverInsertData = [];

        foreach ($circleIdMap as $originalId => $data) {
            foreach ($data['approvers'] as $approver) {

                if (isset($staffs[$approver->id])) {
                    $approverInsertData[] = [
                        "approvalCirclesId" => $data['newId'],
                        "staffsId" => $staffs[$approver->id]->id,
                    ];
                }
            }
        }

        // Bulk insert approvers (only one query)
        if (!empty($approverInsertData)) {
            DB::connection('mysql2')
                ->table('approval_circle_approvers')
                ->insert($approverInsertData);
        }

        return "$this->new_company approval circle done";
    }

    public function findUserWithOldId($oldId)
    {
        return DB::connection('mysql2')->table('staffs')->where('old_user_id', $oldId)->where('company_id', $this->new_company)->first();
    }

    public function findStaffWithEmail($email)
    {
        $user = DB::connection('mysql2')->table('users')->where('email', $email)->first();
        return DB::connection('mysql2')->table('staffs')->where('user_id', $user->id)->where('company_id', $this->new_company)->first();
    }

    public function moveExpenseCategories()
    {
        $com = DB::connection('mysql2')->table('companies')->where('id', $this->new_company)->first();
        if (!$com) {
            return 'New company not found';
        }

        $ab = DB::table('categories')->where('company_id', $this->company_id)->get();
        DB::transaction(function () use ($ab) {
            $indexed = [];
            foreach ($ab as $item) {
                $indexed[$item->id] = $item;
            }
            $tree = [];

            foreach ($indexed as $id => $item) {
                Log::info('index id');
                Log::info(json_encode($item));
                if ($item->parentId != null) {
                    // append to parent's children
                    $indexed[$item->parentId]->children[] = &$indexed[$id];
                } else {
                    // top-level item
                    $tree[] = &$indexed[$id];
                }
            }

            foreach ($tree as $cat) {
                $person = null;
                $group = null;
                if ($cat->userId) {
                    $staff = $this->findUserWithOldId($cat->userId);
                    if ($staff) {
                        $person = $staff->id;
                    }
                }
                if ($cat->groupId) {
                    //handle group
                    $gp = DB::connection('mysql2')->table('groups')->where('company_id', $this->new_company)->where('old_group_id', $cat->groupId)->first();
                    if ($gp) {
                        $group = $gp->id;
                    }
                }
                $id = DB::connection('mysql2')->table('budget_categories')->insertGetId([
                    'name' => $cat->title ?? '',
                    'description' => $cat->description,
                    'company_id' => $this->new_company,
                    'budget' => $cat->target * 100,
                    'budget_type' => $cat->type == 'month' ? 'monthly' : $cat->type,
                    'budget_manager' => $cat->userId ? 'person' : ($cat->groupId ? 'group' : 'organization'),
                    'has_subcategory' => isset($cat->children) && count($cat->children) > 0 ? true : false,
                    'can_overspend' => $cat->overspend,
                    'person_manager_id' => $person,
                    'group_manager_id' => $group,
                    'currency_id' => 1,
                    'created_at' => $cat->createdAt,
                    'updated_at' => $cat->updatedAt,
                    'deleted_at' => $cat->isDeleted || $cat->deletedAt ? ($cat->deletedAt ? $cat->deletedAt : now()) : null,
                    'old_budget_id' => $cat->id,
                ]);
                if (isset($cat->children) && count($cat->children) > 0) {
                    foreach ($cat->children as $sub) {
                        DB::connection('mysql2')->table('budget_sub_categories')->insert([
                            'name' => $sub->title ?? '',
                            'budget' => $sub->target * 100,
                            'category_id' => $id,
                            'old_sub_budget_id' => $sub->id,
                            'created_at' => $sub->createdAt,
                            'updated_at' => $sub->updatedAt,
                            'deleted_at' => $sub->isDeleted || $sub->deletedAt ? ($sub->deletedAt ? $sub->deletedAt : now()) : null,
                        ]);
                    }
                }
            }
        });
        return "$this->new_company expense categories done";
    }

    public function movePayments()
    {
        $payments = DB::table('payments')->where('company_id', $this->company_id)->get();
        // return $payments;

        $default_budget = DB::connection('mysql2')->table('budget_categories')->where('name', 'Unclassified')->where('company_id', $this->new_company)->first();
        // return $default_budget;
        foreach ($payments as $pay) {
            DB::transaction(function () use ($pay, $default_budget) {
                Log::info("payment - $pay->id");
                $status = $this->paymentStatus($pay->status);
                $endo_queue = DB::table('endorsement_queue')->where('company_id', $this->company_id)->where('mode', 'Payment')->where('mode_id', $pay->id)->get();
                $appr_queue = DB::table('approval_queue')->where('company_id', $this->company_id)->where('mode', 'Payment')->where('order_id', $pay->id)->get();
                if ($status == 'awaiting_approval') {
                    if (count($endo_queue) > 0 && $endo_queue->contains('endorsement_status', 3)) {
                        $status = 'awaiting_endorsement';
                    }
                }
                $recurring_frequency = null;
                $recurring_interval = null;
                if ($pay->recurringPeriod) {
                    $rp = json_decode($pay->recurringPeriod);
                    Log::info('recurring');
                    Log::info($rp->recurringFrequency);
                    $recurring_interval = $rp->recurringFrequency;
                    $recurring_frequency = $rp->recurringMode;
                }
                $raised_by = $this->findUserWithOldId($pay->paid_by);
                $bud_id = $default_budget->id;
                $sub_bud_id = null;
                if ($pay->category && $pay->category != 1) {
                    $bud = DB::connection('mysql2')->table('budget_categories')->where('old_budget_id', $pay->category)->where('company_id', $this->new_company)->first();
                    if (!$bud) {
                        $sub_bud = DB::connection('mysql2')->table('budget_sub_categories')->where('old_sub_budget_id', $pay->category)->first();
                        // if()
                        $sub_bud_id = $sub_bud->id;
                        $bud_id = $sub_bud->category_id;
                    } else {
                        $bud_id = $bud->id;
                    }
                }

                $payee = null;
                if ($pay->payee_id != "0" && $pay->payee_id != 0) {
                    $pd = DB::connection('mysql2')->table('payees')->where('old_payee_id', $pay->payee_id)->first();
                    $payee = $pd->id;
                }


                $d = Carbon::parse($pay->due_date);
                if ($d->year < 1000) {
                    $d->year = 2023; // or any fallback year
                }

                $due_date = $d->format('Y-m-d H:i:s');

                $pid = DB::connection('mysql2')->table('payment_requests')->insertGetId([
                    'company_id' => $this->new_company,
                    'payment_number' => $pay->payment_no ?? 20001,
                    'amount' => $pay->total * 100,
                    'status' => $status,
                    'business_purpose' => $pay->business_purpose,
                    'attachments' => $pay->attachment &&  $pay->attachment != '' ? json_encode([["url" => $pay->attachment, "tags" => [], "type" => "upload", "bytes" => 120814, "folder" => "", "secure_url" => $pay->attachment, "placeholder" => false, "resource_type" => "raw", "original_filename" => "document"]]) : null,
                    'type' => !$pay->type || $pay->type == 'recurring' ? 'online' : $pay->type,
                    'is_recurring' => $pay->recurringPeriod ? true : false,
                    'recurring_frequency' => "monthly",
                    'recurring_interval' => $recurring_interval,
                    'bsmp_ref' => $pay->ref,
                    'bank_ref' => $pay->sessionID,
                    'session_id' => $pay->sessionID,

                    'due_at' => $due_date,
                    'paid_at' => $pay->paidAt,
                    'left_at' => $pay->leftAt,
                    'created_at' => $pay->created_at,
                    'updated_at' => !$pay->updated_at || $pay->updated_at == '0000-00-00 00:00:00'  ? $pay->created_at : $pay->updated_at,
                    "raised_by_id" => $pay->paid_by == 828 ? 1 : $raised_by->id,
                    "payee_id" => $payee,
                    'metadata' => json_encode(["payee" => ["payee_name" => $pay->payee_name], "monnify_response" => $pay->method_ref]),
                    "budget_category_id" => $bud_id,
                    "budget_sub_category_id" => $sub_bud_id,
                    "currency_id" => 1,
                    "retry" => $pay->retries,
                    "total" => $pay->total * 100,
                    'method' => $pay->method,
                    'old_payment_id' => $pay->id
                ]);

                // save endorsers
                if (count($endo_queue) > 0) {
                    foreach ($endo_queue as $endorser) {
                        $user = DB::connection('mysql2')->table('users')->where('email', $endorser->EXECUTOR_ID)->first();
                        $staff = DB::connection('mysql2')->table('staffs')->where('user_id', $user->id)->where('company_id', $this->new_company)->first();

                        DB::connection('mysql2')->table('payment_requests_endorsers')->insert([
                            'paymentRequestsId' => $pid,
                            'staffsId' => $staff->id,
                        ]);
                        DB::connection('mysql2')->table('request_queue')->insert([
                            'entity_type' => 'Entity/Payment',
                            'entity_id' => $pid,
                            'level' => 'endorsement',
                            'notes' => $endorser->endorsement_notes,
                            'status' => $endorser->endorsement_status == 2 ? 'declined' : ($endorser->endorsement_status == 1 ? 'endorsed' : ($endorser->endorsement_status == 3 ? 'hidden' : 'pending')),
                            'created_at' => $endorser->DATE_ADDED,
                            'updated_at' => !$endorser->DATE_MODIFIED ? $endorser->DATE_ADDED : $endorser->DATE_MODIFIED,
                            'actioned_by_id' => $staff->id,
                            'added_by_id' => $pay->paid_by == 828 ? 1 : $raised_by->id,
                            'company_id' => $this->new_company
                        ]);
                    }
                }

                // save approvers
                if (count($appr_queue) > 0) {
                    foreach ($appr_queue as $approver) {
                        if ($approver->EXECUTOR_ID != "undefined") {
                            $user = DB::connection('mysql2')->table('users')->where('email', $approver->EXECUTOR_ID)->first();
                            $staff = DB::connection('mysql2')->table('staffs')->where('user_id', $user->id)->where('company_id', $this->new_company)->first();

                            DB::connection('mysql2')->table('payment_requests_approvers')->insert([
                                'paymentRequestsId' => $pid,
                                'staffsId' => $staff->id,
                            ]);
                            DB::connection('mysql2')->table('request_queue')->insert([
                                'entity_type' => 'Entity/Payment',
                                'entity_id' => $pid,
                                'level' => 'approval',
                                'notes' => $approver->APPROVAL_NOTES,
                                'status' => $approver->APPROVAL_STATUS == 2 ? 'declined' : ($approver->APPROVAL_STATUS == 1 ? 'approved' : ($approver->APPROVAL_STATUS == 3 ? 'hidden' : 'pending')),
                                'created_at' => $approver->DATE_ADDED,
                                'updated_at' => !$approver->DATE_MODIFIED ? $approver->DATE_ADDED : $approver->DATE_MODIFIED,
                                'actioned_by_id' => $staff->id,
                                'added_by_id' => $pay->paid_by == 828 ? 1  : $raised_by->id,
                                'company_id' => $this->new_company
                            ]);
                        }
                    }
                }

                // save cc

                if ($pay->cc && $pay->cc != 'null' && $pay->cc != '""' && $pay->cc != "") {
                    $ccs = json_decode($pay->cc);
                    foreach ($ccs as $cc) {
                        $st = $this->findStaffWithEmail($cc);
                        DB::connection('mysql2')->table('payment_requests_cc')->insert([
                            'paymentRequestsId' => $pid,
                            'staffsId' => $st->id
                        ]);
                    }
                }

                // save activity
                $activities = DB::table('document_activity')->where('doc_type', 'Payment')->where('document_id', $pay->id)->get();

                foreach ($activities as $act) {
                    $act_user = $raised_by;
                    if ($act->user_id != 0) {
                        $act_user = $this->findUserWithOldId($act->user_id);
                    }
                    DB::connection('mysql2')->table('request_activities')->insert([
                        'entity_id' => $pid,
                        'entity_type' => 'Entity/Payment',
                        'action' => $this->paymentActivityMode($act->activity_mode),
                        'action_note' => $act->activity,
                        'created_at' => $act->activity_date,
                        'actioned_by_id' => $pay->paid_by == 828 ? 1  : $act_user->id
                    ]);
                }
            });
        }
        return "$this->new_company Payments done";
    }

    public function movePurchases()
    {
        $purchases = DB::table('order')->where('company_id', $this->company_id)->get();

        $default_budget = DB::connection('mysql2')->table('budget_categories')->where('name', 'Unclassified')->where('company_id', $this->new_company)->first();
        $count = 1;
        foreach ($purchases as $pay) {
            DB::transaction(function () use ($pay, $default_budget, $count) {
                Log::info("purchases - $pay->id");
                $type = 'bill';
                $merchant = 'vtpass';
                if ($pay->merchant == 'Jumia' || $pay->merchant == 'Konga') {
                    $type = $pay->merchant;
                    $products = DB::table('order_products')->where('order_id', $pay->id)->get();
                } else {
                    $products = DB::table('utilityItems')->where('orderId', $pay->id)->get();
                }
                $status = $this->purchaseStatus($pay->approval_status);
                $endo_queue = DB::table('endorsement_queue')->where('company_id', $this->company_id)->where('mode', NULL)->where('mode_id', $pay->id)->get();
                $appr_queue = DB::table('approval_queue')->where('company_id', $this->company_id)->where('mode', NULL)->where('order_id', $pay->id)->get();
                if ($status == 'awaiting_approval') {
                    if (count($endo_queue) > 0 && $endo_queue->contains('endorsement_status', 3)) {
                        $status = 'awaiting_endorsement';
                    }
                }
                $raised_by = $this->findStaffWithEmail($pay->order_by);
                $bud_id = $default_budget->id;
                $sub_bud_id = null;


                if (count($products) > 0) {
                    foreach ($products as $key => $prod) {
                        $cor_type = 'airtime';
                        $provider = 'mtn';
                        $fee = 0;
                        $amount = 0;
                        $commission = 0;
                        $metadata = [];
                        $updated = $pay->date;
                        if ($type == 'Jumia' || $type == 'Konga') {
                            $amount = $prod->price;
                            $cor_type = strtolower($type);
                            $provider = strtolower($type);
                            $merchant = strtolower($pay->merchant);
                            $metadata = [
                                'phone' => $pay->phone,
                                'store' => [
                                    'description' => $prod->description,
                                    'quantity' => $prod->quantity,
                                    'price' => $prod->price,
                                    'shipping' => $prod->shipping,
                                    'tax' => $prod->tax,
                                    'phone' => $pay->phone,
                                    'shipping_date' => $pay->shipping_date,
                                    'delivery_address' => $pay->delivery_address,
                                    'city' => $pay->city,
                                    'state' => $pay->state,
                                    'purchase_order_request' => $pay->purchase_order_request,
                                ]
                            ];
                        } else {
                            $amount = $prod->amount;
                            $commission = $prod->commission ?? 0;
                            $updated = $prod->updatedAt;
                            if ($prod->method) {
                                $merchant = strtolower($prod->method);
                            }
                            if ($prod->convinienceFee) {
                                $fee = $prod->convinienceFee;
                            }
                            if ($prod->category) {
                                $bud = DB::connection('mysql2')->table('budget_categories')->where('old_budget_id', $prod->category)->where('company_id', $this->new_company)->first();
                                if (!$bud) {
                                    $sub_bud = DB::connection('mysql2')->table('budget_sub_categories')->where('old_sub_budget_id', $prod->category)->first();
                                    // if()
                                    $sub_bud_id = $sub_bud->id;
                                    $bud_id = $sub_bud->category_id;
                                } else {
                                    $bud_id = $bud->id;
                                }
                            }

                            if ($prod->type) {
                                $cor_type = $prod->type;
                                $provider = strtolower($prod->utilityName);
                            } else {
                                if ($prod->utilityName) {
                                    if (str_contains($prod->utilityName, 'electric')) {
                                        $cor_type = 'electricity_bill';
                                    } elseif ($prod->utilityName == 'MTN' || $prod->utilityName == 'GLO' || $prod->utilityName == 'AIRTEL' || $prod->utilityName == '9MOBILE') {
                                        $cor_type = 'airtime';
                                    } elseif (str_contains($prod->utilityName, 'tv')) {
                                        $cor_type = 'tv_subscription';
                                    } else {
                                        $cor_type = 'data';
                                    }
                                    $provider = strtolower($prod->utilityName);
                                } else {
                                    if (!$prod->productInfo || empty((array)$prod->productInfo)) {
                                        if ($prod->amount < 500) {
                                            $cor_type = 'airtime';
                                            $provider = $prod->utilityName;
                                        }
                                    } else {
                                        if ($pay->merchant == 'airtime') {
                                            $cor_type = 'airtime';
                                            $provider = $prod->utilityName;
                                        } else {
                                            $decode = json_decode($prod->productInfo);
                                            if (str_contains($decode->variation_code, 'tv')) {
                                                $cor_type = 'tv_subscription';
                                                $provider = strtolower($decode->variation_code);
                                            } else {
                                                $cor_type = $pay->merchant;
                                            }
                                        }
                                    }
                                }
                            }
                            $metadata = [
                                'phone' => $pay->phone,
                                'provider_response' => $prod->methodRef,
                            ];
                            if ($prod->productInfo && !empty((array)$prod->productInfo)) {
                                array_merge($metadata, (array)$prod->productInfo);
                            }
                            if ($cor_type == 'electricity_bill') {
                                $metadata['meter_no'] = $prod->utilityProductCode;
                            }
                        }

                        $pid = DB::connection('mysql2')->table('purchases')->insertGetId([
                            'company_id' => $this->new_company,
                            'purchase_number' => $key > 0 ? 3000001 + $count : $pay->order_no,
                            'amount' => $amount * 100,
                            'status' => $status,
                            'business_purpose' => $pay->summary . ' /n ' . $pay->detail,
                            'country' => NULL,
                            'type' => $cor_type,
                            'provider' => $provider,
                            'merchant' => $merchant,
                            'fee' => $fee * 100,
                            'other_fees' => NULL,
                            'due_at' => $pay->date,
                            'bsmp_ref' => NULL,
                            'recipient_name' => NULL,
                            "budget_category_id" => $bud_id,
                            "budget_sub_category_id" => $sub_bud_id,
                            'commission' => $commission * 100,
                            'old_purchase_id' => $pay->id,
                            'created_at' => $pay->date,
                            'updated_at' => $updated,
                            "raised_by" => $raised_by->id,
                            'total' => (floatval($amount) + floatval($fee)) * 100,
                            'metadata' => json_encode($metadata),
                        ]);

                        // save endorsers
                        if (count($endo_queue) > 0) {
                            foreach ($endo_queue as $endorser) {
                                $user = DB::connection('mysql2')->table('users')->where('email', $endorser->EXECUTOR_ID)->first();
                                $staff = DB::connection('mysql2')->table('staffs')->where('user_id', $user->id)->where('company_id', $this->new_company)->first();

                                DB::connection('mysql2')->table('purchase_requests_endorsers')->insert([
                                    'purchaseRequestId' => $pid,
                                    'staffsId' => $staff->id,
                                ]);
                                DB::connection('mysql2')->table('request_queue')->insert([
                                    'entity_type' => 'Entity/Purchase',
                                    'entity_id' => $pid,
                                    'level' => 'endorsement',
                                    'notes' => $endorser->endorsement_notes,
                                    'status' => $endorser->endorsement_status == 2 ? 'declined' : ($endorser->endorsement_status == 1 ? 'endorsed' : ($endorser->endorsement_status == 3 ? 'hidden' : 'pending')),
                                    'created_at' => $endorser->DATE_ADDED,
                                    'updated_at' => !$endorser->DATE_MODIFIED ? $endorser->DATE_ADDED : $endorser->DATE_MODIFIED,
                                    'actioned_by_id' => $staff->id,
                                    'added_by_id' => $raised_by->id,
                                    'company_id' => $this->new_company
                                ]);
                            }
                        }

                        // save approvers
                        if (count($appr_queue) > 0) {
                            foreach ($appr_queue as $approver) {
                                if ($approver->EXECUTOR_ID != "undefined") {
                                    $user = DB::connection('mysql2')->table('users')->where('email', $approver->EXECUTOR_ID)->first();
                                    $staff = DB::connection('mysql2')->table('staffs')->where('user_id', $user->id)->where('company_id', $this->new_company)->first();

                                    DB::connection('mysql2')->table('purchase_requests_approvers')->insert([
                                        'purchaseRequestId' => $pid,
                                        'staffsId' => $staff->id,
                                    ]);
                                    DB::connection('mysql2')->table('request_queue')->insert([
                                        'entity_type' => 'Entity/Purchase',
                                        'entity_id' => $pid,
                                        'level' => 'approval',
                                        'notes' => $approver->APPROVAL_NOTES,
                                        'status' => $approver->APPROVAL_STATUS == 2 ? 'declined' : ($approver->APPROVAL_STATUS == 1 ? 'approved' : ($approver->APPROVAL_STATUS == 3 ? 'hidden' : 'pending')),
                                        'created_at' => $approver->DATE_ADDED,
                                        'updated_at' => !$approver->DATE_MODIFIED ? $approver->DATE_ADDED : $approver->DATE_MODIFIED,
                                        'actioned_by_id' => $staff->id,
                                        'added_by_id' => $raised_by->id,
                                        'company_id' => $this->new_company
                                    ]);
                                }
                            }
                        }


                        // save activity
                        $activities = DB::table('order_activity')->where('order_id', $pay->id)->get();

                        foreach ($activities as $act) {
                            $act_user = $raised_by;
                            if ($act->user_id != 0) {
                                $act_user = $this->findUserWithOldId($act->user_id);
                            }
                            DB::connection('mysql2')->table('request_activities')->insert([
                                'entity_id' => $pid,
                                'entity_type' => 'Entity/Purchase',
                                'action' => $this->purchaseActivityMode($act->activity_mode),
                                'action_note' => $act->activity,
                                'created_at' => $act->activity_date,
                                'actioned_by_id' => $act_user->id
                            ]);
                        }
                    }
                }
            });
        }
        return "$this->new_company purchases done";
    }

    public function moveWallet()
    {
        $wallet = DB::table('wallet')->where('company_id', $this->company_id)->get();
        // return $wallet;

        foreach ($wallet as $wal) {
            $provider = 'clan';
            if ($wal->method == 'Advace') {
                $provider = 'cash_advance';
            } else if (strtolower($wal->method)  == 'providus') {
                $provider = 'providus';
            } else {
                $provider = 'clan';
            }
            $payload = [
                "data" => [
                    "amount" => $wal->amount,
                    "bankcode" => null,
                    "bankname" => $wal->origin_bank,
                    "currency" => 'NGN',
                    "craccount" => $wal->account_number,
                    "narration" => $wal->narration,
                    "reference" => $wal->reference_id,
                    "sessionid" => $wal->sessionId,
                    "created_at" => $wal->date,
                    "craccountname" => null,
                    "originatorname" => $wal->origin_accountname,
                    "paymentreference" => $wal->reference_id,
                    "settlementId" => $wal->settlementId,
                    "originatoraccountnumber" => $wal->origin_accountno,
                ],
                "txref" => '',
                "status" => true,
                "message" => 'Transaction successful',
                "request_id" => '',
                "transactionreference" => '',
            ];
            DB::connection('mysql2')->table('webhooks')->insert([
                'company_id' => $this->new_company,
                'old_wallet_id' => $wal->id,
                'provider' => $provider,
                'title' => 'walletFund',
                'payload' => json_encode($payload),
                'created_at' => $wal->date,
                'updated_at' => $wal->date,
                'reference_no' => $wal->reference_id,
                'amount' => $wal->amount * 100,
                'account_number' => $wal->account_number,
                'origin_account_number' => $wal->origin_accountno,
                'origin_account_name' => $wal->origin_accountname,
                'origin_bank' => $wal->origin_bank,
                'narration' => $wal->narration,
            ]);
        }
        return "$this->new_company wallet done";
    }

    public function moveBilling()
    {
        $bills = DB::table('billings')->where('companyId', $this->company_id)->get();
        foreach ($bills as $bill) {
            Log::info("Billing - $bill->id");
            $billing_category_id = 1;
            $amount_paid = 0;
            if ($bill->billingCategoryId == 4) {
                $billing_category_id = 1;
                $amount_paid = 0;
            } else if ($bill->billingCategoryId == 14) {
                $billing_category_id = 6;
                $amount_paid = 2000000;
            } else if ($bill->billingCategoryId == 24) {
                $billing_category_id = 7;
                $amount_paid = 2687500;
            } else if ($bill->billingCategoryId == 34 || $bill->billingCategoryId == 44) {
                $billing_category_id = 8;
                $amount_paid = 0;
            } else if ($bill->billingCategoryId == 54) {
                $billing_category_id = 5;
                $amount_paid = 1075000;
            } else if ($bill->billingCategoryId == 64) {
                $billing_category_id = 4;
                $amount_paid = 4687500;
            } else if ($bill->billingCategoryId == 74) {
                $billing_category_id = 2;
                $amount_paid = 1750000;
            }

            $raised_by = 1;
            $user = DB::connection('mysql2')->table('staffs')->where('old_user_id', $bill->createdBy)->first();
            if ($user) {
                $raised_by = $user->id;
            }
            DB::connection('mysql2')->table('billings')->insert([
                "duration" => $bill->duration,
                "has_expired" => $bill->billingEnd < now() ? true : false,
                "refunded" => false,
                "billing_end_at" => $bill->billingEnd,
                "created_at" => $bill->createdAt,
                "updated_at" => $bill->updatedAt,
                "billing_category_id" => $billing_category_id,
                "company_id" => $this->new_company,
                "billing_start_at" => $bill->billingStart,
                "raised_by_id" => $raised_by,
                "total_spent" => $bill->totalSpent * 100,
                "amount_refunded" => $bill->refund,
                "amount_paid" => $amount_paid,
                "old_billing_id" => $bill->id
            ]);
        }

        return "$this->new_company billing done";;
    }

    public function moveTransactions()
    {
        $trans = DB::table('transactions')->where('company_id', $this->company_id)->get();
        // return $trans;

        foreach ($trans as $tran) {
            DB::transaction(function () use ($tran) {
                Log::info("transaction - $tran->id");
                $entity_id = 0;
                $entity_type = 'Entity/payment';
                $entity_mode = 'online';
                $business_purpose = $tran->business_purpose;
                $due_at = $tran->date;
                $fee = $tran->fees ? $tran->fees : 0;
                if ($tran->mode == 1) {
                    $entity_type = 'wallet';
                    $entity_mode = 'wallet';
                    $eid = DB::connection('mysql2')->table('webhooks')->where('company_id', $this->new_company)->where('old_wallet_id', $tran->mode_id)->first();
                    if ($eid) {
                        $entity_id = $eid->id;
                        $due_at = $eid->created_at;
                    } else {
                        Log::info("Wallet mode id for Wallet credit of - $tran->mode_id not found - transaction -$tran->id");
                    }
                } elseif ($tran->mode == 2) {
                    $entity_type = 'Entity/purchase';

                    $eid = DB::connection('mysql2')->table('purchases')->where('company_id', $this->new_company)->where('old_purchase_id', $tran->mode_id)->first();
                    if ($eid) {
                        $entity_id = $eid->id;
                        $entity_mode = $eid->type;
                        $business_purpose = $eid->business_purpose;
                        $due_at = $eid->due_at;
                    } else {
                        Log::info("Wallet mode id for Purchase of - $tran->mode_id not found - transaction -$tran->id");
                    }
                } elseif ($tran->mode == 6) {
                    $entity_type = 'subscription';
                    $entity_mode = 'subscription';
                    $eid = DB::connection('mysql2')->table('billings')->where('company_id', $this->new_company)->where('old_billing_id', $tran->mode_id)->first();
                    if ($eid) {
                        $entity_id = $eid->id;
                    } else {
                        Log::info("Wallet mode id for Billing of - $tran->mode_id not found - transaction -$tran->id");
                    }
                } elseif ($tran->mode == 7) {
                    $entity_type = 'refund';
                    $entity_mode = 'refund';
                } else {
                    $entity_type = 'Entity/payment';
                    $eid = DB::connection('mysql2')->table('payment_requests')->where('company_id', $this->new_company)->where('old_payment_id', $tran->mode_id)->first();
                    if ($eid) {
                        $entity_id = $eid->id;
                        $entity_mode = $eid->type;
                        $business_purpose = $eid->business_purpose;
                        $due_at = $eid->due_at;
                    } else {
                        Log::info("Wallet mode id for Payment of - $tran->mode_id not found - transaction -$tran->id");
                    }
                }

                $amount = $fee > 0 ? floatval($tran->amount - $fee) * 100 : $tran->amount * 100;
                DB::connection('mysql2')->table('transactions')->insert([
                    'amount' => $amount,
                    'mode' => $tran->status == 1 || $tran->status == 3 ? 'credit' : 'debit',
                    'entity_type' => $entity_type,
                    'entity_id' => $entity_id,
                    'entity_mode' => $entity_mode,
                    'status' => 'success',
                    'business_purpose' => $business_purpose,
                    'transaction_number' => $tran->transaction_no,
                    'created_at' => $tran->date,
                    'due_at' => $due_at,
                    'updated_at' => $tran->updatedAt ?? now(),
                    'currency_id' => 1,
                    'company_id' => $this->new_company,
                    'fee' => floatval($fee) * 100,
                    'total' => (floatval($fee) * 100) + $amount,
                    'bsmp_ref' => 'BSMP' . Str::ulid(),
                    'balance_after' => $tran->bal_after ? floatval($tran->bal_after) * 100 : 0,
                    'old_transaction_id' => $tran->id
                ]);
            });
        }

        return "$this->new_company transactions done";
    }

    public function migrateAll()
    {
        \App\Jobs\RunMigration::dispatch($this->company_ids);

        return response()->json([
            'status'      => 'queued',
            'message'     => 'Migration is running in the background.',
            'company_ids' => $this->company_ids,
        ]);
    }

    public function paymentStatus($status)
    {
        $ps = 'unknown';
        switch ($status) {
            case 0:
                $ps = 'draft';
                break;
            case 1:
                $ps = 'awaiting_approval';
                break;
            case 2:
                $ps = 'approved';
                break;
            case 3:
                $ps = 'declined';
                break;
            case 4:
                $ps = 'processed';
                break;
            case 5:
                $ps = 'error_in_payment';
                break;
            case 6:
                $ps = 'awaiting_funds';
                break;
            case 7:
                $ps = 'awaiting_due_date';
                break;
            case 8:
                $ps = 'paused';
                break;
            case 9:
                $ps = 'processing';
                break;
            case 10:
                $ps = 'reversed';
                break;
            case 11:
                $ps = 'canceled';
                break;
            case 99:
                $ps = 'archived';
                break;
            default:
                $ps = 'unknown';
        }

        return $ps;
    }

    public function purchaseStatus($status)
    {
        $ps = 'unknown';
        switch ($status) {
            case 0:
                $ps = 'awaiting_approval';
                break;
            case 1:
                $ps = 'approved';
                break;
            case 2:
                $ps = 'declined';
                break;
            case 3:
                $ps = 'draft';
                break;
            case 4:
                $ps = 'awaiting_funds';
                break;
            case 5:
                $ps = 'processed';
                break;
            case 6:
                $ps = 'processing';
                break;
            case 7:
                $ps = 'processed';
                break;
            case 8:
                $ps = 'reversed';
                break;
            case 9:
                $ps = 'refund';
                break;
            case 10:
                $ps = 'failed';
                break;
            case 11:
                $ps = 'processed';
                break;
            case 12:
                $ps = 'processed';
                break;
            case 13:
                $ps = 'processed';
                break;
            case 14:
                $ps = 'failed';
                break;
            default:
                $ps = 'unknown';
        }

        return $ps;
    }


    public function paymentActivityMode($mode)
    {
        $md = 'OTHERS_ACTIVITY';
        switch ($mode) {
            case 5:
                $md = 'APPROVER_PAYMENT_REQUEST';
                break;
            case 6:
                $md = 'DISBURSED_PAYMENT_REQUEST';
                break;
            case 7:
                $md = 'DECLINE_PAYMENT_REQUEST';
                break;
            case 8:
                $md = 'CREATE_PAYMENT_REQUEST';
                break;
            case 9:
                $md = 'SUBMIT_PAYMENT_REQUEST';
                break;
            default:
                $md = 'OTHERS_ACTIVITY';
        }

        return $md;
    }

    public function purchaseActivityMode($mode)
    {
        $md = 'OTHERS_ACTIVITY';
        switch ($mode) {
            case 5:
                $md = 'APPROVER_PURCHASE_REQUEST';
                break;
            case 6:
                $md = 'APPROVER_PURCHASE_REQUEST';
                break;
            case 7:
                $md = 'DECLINE_PURCHASE_REQUEST';
                break;
            case 8:
                $md = 'CREATE_PURCHASE_REQUEST';
                break;
            case 9:
                $md = 'SUBMIT_PURCHASE_REQUEST';
                break;
            default:
                $md = 'OTHERS_ACTIVITY';
        }

        return $md;
    }

    public function updateApprovalCircleStatus()
    {
        $companies = DB::connection('mysql2')->table('companies')->whereNotNull('old_company_id')->get();
        foreach ($companies as $company) {
            $setting = DB::table('settings')
                ->where('companyId', $company->old_company_id)
                ->latest('id')->first();

            $pref = json_decode($setting->preferences);

            Log::info("Company - $company->name - approval circle status - " . ($pref->approvalCircle ?? 'not found'));

            if ($pref->approvalCircle) {
                DB::connection('mysql2')->table('companies_preferences')->where('companiesId', $company->id)->where('preferencesId', 16)->update([
                    'status' => true,
                    'visible' => true
                ]);
            } else {
                DB::connection('mysql2')->table('companies_preferences')->where('companiesId', $company->id)->where('preferencesId', 16)->update([
                    'visible' => true
                ]);
            }
        }
        return $companies;
    }
}
