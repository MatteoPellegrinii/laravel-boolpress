<?php

namespace App\Http\Controllers\Admin;

use App\Models\Tag;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    protected $validation_rules = [
        'title'         => 'required|string|max:100',
        'slug'          => [
            'required',
            'string',
            'max:100',
        ],
        'category_id'   => 'required|integer|exists:categories,id',
        'tags'          => 'nullable|array',
        'tags.*'        => 'integer|exists:tags,id',
        // 'image'         => 'required_without:content|nullable|url',
        'image'         => 'required_without:content|nullable|file|image|max:1024', // dimensione max in kilobytes
        'content'       => 'required_without:image|nullable|string|max:5000',
        'excerpt'       => 'nullable|string|max:200',
    ];

    protected $perPage = 20;


    // Display a listing of the resource.
    public function index()
    {
        $posts = Post::paginate($this->perPage);
        return view('admin.posts.index', compact('posts'));
    }


    public function myIndex() {
        $posts = Auth::user()->posts()->paginate($this->perPage);
        return view('admin.posts.index', compact('posts'));
    }


    // Show the form for creating a new resource.
    public function create()
    {
        $categories = Category::all();
        $tags = Tag::all();

        return view('admin.posts.create', [
            'categories'    => $categories,
            'tags'          => $tags,
        ]);
    }


    // Store a newly created resource in storage.
    public function store(Request $request)
    {
        // dd($request->all());
        // validation
        $this->validation_rules['slug'][] = 'unique:posts';
        $request->validate($this->validation_rules);

        $data = $request->all();

        if (key_exists('image', $data)) {
            // salvare l'immagine in public
            $img_path = Storage::put('uploads', $data['image']);

            // aggiornare il valore della chiave image con il nome dell'immagine appena creata
            $data['image'] = $img_path;
        }

        $data = $data + [
            'user_id'       => Auth::id(),
        ];
        // dump($data);
        // dump(Auth::user());

        // salvataggio
        $post = Post::create($data);
        $post->tags()->sync($data['tags']);

        return redirect()->route('admin.posts.show', ['post' => $post]);
        // redirect
    }


    // Display the specified resource.
    public function show(Post $post)
    {
        return view('admin.posts.show', compact('post'));
    }


    // Show the form for editing the specified resource.
    public function edit(Post $post)
    {
        if (Auth::id() != $post->user_id) abort(401);
        $categories = Category::all();
        $tags = Tag::all();

        return view('admin.posts.edit', [
            'post'          => $post,
            'categories'    => $categories,
            'tags'          => $tags,
        ]);
    }


    // Update the specified resource in storage.
    public function update(Request $request, Post $post)
    {
        if (Auth::id() != $post->user_id) abort(401);

        // validation
        $this->validation_rules['slug'][] = Rule::unique('posts')->ignore($post->id);
        $request->validate($this->validation_rules);
        $data = $request->all();

        if (key_exists('image', $data)) {
            // eliminare il file precedente se esiste
            if ($post->image) {
                Storage::delete($post->image);
            }

            // caricare il nuovo file
            $img_path = Storage::put('uploads', $data['image']);

            // aggiornare l'array $data con il percorso del file appena creato
            $data['image'] = $img_path;
        }

        // aggiornare nel database
        $post->update($data);
        $post->tags()->sync($data['tags']);

        // redirect
        return redirect()->route('admin.posts.show', ['post' => $post]);
    }


    // Remove the specified resource from storage.
    public function destroy(Post $post)
    {
        if (Auth::id() != $post->user_id) abort(401);

        // TODO: inplement soft deleting
        // $post->tags()->sync([]); // equivalente a detach()
        $post->tags()->detach();
        $post->delete();

        return redirect()->route('admin.posts.index')->with('deleted', "Il post <strong>{$post->title}</strong> ?? stato eliminato");
    }

    public function getSlug(Request $request) {
        // /admin/getslug?title=Questo ?? il titolo
        $title = $request->query('title');
        $slug = Post::getSlug($title);

        return response()->json([
            'success'   => true,
            'response'  => $slug
        ]);
    }
}
