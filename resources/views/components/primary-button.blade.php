{{-- resources/views/components/primary-button.blade.php --}}
<button {{ $attributes->merge([
  'class' => 'inline-flex items-center px-4 py-2 rounded-2xl font-semibold
              bg-indigo-600 text-white hover:bg-indigo-700
              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2'
]) }}>
  {{ $slot }}
</button>
