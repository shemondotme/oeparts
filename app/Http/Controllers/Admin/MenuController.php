<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MenuLocation;
use App\Enums\MenuTarget;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    /**
     * Display a list of menus with their items.
     */
    public function index()
    {
        $menus = Menu::with(['items' => function ($query) {
            $query->orderBy('parent_id')->orderBy('sort_order');
        }])->latest()->get();

        return view('admin.cms.menus.index', [
            'menus' => $menus,
            'locations' => MenuLocation::cases(),
        ]);
    }

    /**
     * Show the form for creating a new menu.
     */
    public function create()
    {
        return view('admin.cms.menus.create', [
            'locations' => MenuLocation::cases(),
            'pages' => Page::where('status', 'published')->get(),
        ]);
    }

    /**
     * Store a newly created menu in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', Rule::enum(MenuLocation::class)],
            'is_active' => ['boolean'],
        ]);

        Menu::create($validated);

        return redirect()->route('admin.cms.menus.index')
            ->with('success', __('Menu created successfully.'));
    }

    /**
     * Display the specified menu with its items.
     */
    public function show(Menu $menu)
    {
        $menu->load(['items' => function ($query) {
            $query->orderBy('parent_id')->orderBy('sort_order');
        }]);

        return view('admin.cms.menus.show', [
            'menu' => $menu,
        ]);
    }

    /**
     * Show the form for editing the specified menu.
     */
    public function edit(Menu $menu)
    {
        $menu->load(['items' => function ($query) {
            $query->orderBy('parent_id')->orderBy('sort_order');
        }]);

        return view('admin.cms.menus.edit', [
            'menu' => $menu,
            'locations' => MenuLocation::cases(),
            'pages' => Page::where('status', 'published')->get(),
            'targets' => MenuTarget::cases(),
        ]);
    }

    /**
     * Update the specified menu in storage.
     */
    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', Rule::enum(MenuLocation::class)],
            'is_active' => ['boolean'],
        ]);

        $menu->update($validated);

        return redirect()->route('admin.cms.menus.index')
            ->with('success', __('Menu updated successfully.'));
    }

    /**
     * Remove the specified menu from storage.
     */
    public function destroy(Menu $menu)
    {
        $menu->items()->delete();
        $menu->delete();

        return redirect()->route('admin.cms.menus.index')
            ->with('success', __('Menu deleted successfully.'));
    }

    /**
     * Store a new menu item.
     */
    public function storeItem(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'label' => ['required', 'array'],
            'label.*' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:page,url,custom'],
            'page_id' => ['required_if:type,page', 'exists:pages,id'],
            'url' => ['required_if:type,url,custom', 'nullable', 'string', 'max:500'],
            'target' => ['required', Rule::enum(MenuTarget::class)],
            'parent_id' => ['nullable', 'exists:menu_items,id'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $validated['menu_id'] = $menu->id;

        MenuItem::create($validated);

        return redirect()->route('admin.cms.menus.edit', $menu)
            ->with('success', __('Menu item added successfully.'));
    }

    /**
     * Update a menu item.
     */
    public function updateItem(Request $request, Menu $menu, MenuItem $item)
    {
        $validated = $request->validate([
            'label' => ['required', 'array'],
            'label.*' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:page,url,custom'],
            'page_id' => ['required_if:type,page', 'exists:pages,id'],
            'url' => ['required_if:type,url,custom', 'nullable', 'string', 'max:500'],
            'target' => ['required', Rule::enum(MenuTarget::class)],
            'parent_id' => ['nullable', 'exists:menu_items,id'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $item->update($validated);

        return redirect()->route('admin.cms.menus.edit', $menu)
            ->with('success', __('Menu item updated successfully.'));
    }

    /**
     * Delete a menu item.
     */
    public function destroyItem(Menu $menu, MenuItem $item)
    {
        $item->delete();

        return redirect()->route('admin.cms.menus.edit', $menu)
            ->with('success', __('Menu item deleted successfully.'));
    }

    /**
     * Reorder menu items via AJAX.
     */
    public function reorder(Request $request, Menu $menu)
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'exists:menu_items,id'],
            'order.*.parent_id' => ['nullable', 'exists:menu_items,id'],
            'order.*.sort_order' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->order as $item) {
                MenuItem::where('id', $item['id'])->update([
                    'parent_id' => $item['parent_id'] ?? null,
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return response()->json(['success' => true]);
    }
}