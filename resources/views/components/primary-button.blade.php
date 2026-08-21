<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-lg border border-transparent bg-[#0f2d4a] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition ease-in-out duration-150 hover:bg-[#16406a] focus:bg-[#16406a] focus:outline-none focus:ring-2 focus:ring-[#0f2d4a]/40 focus:ring-offset-2 active:bg-[#0c243c]']) }}>
    {{ $slot }}
</button>
