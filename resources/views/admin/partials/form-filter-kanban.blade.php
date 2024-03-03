<select name="setor" id="select-setores">
    <option value="">Filtrar por Setor</option>
    @foreach ($divisions as $division)
        <option value="{{ $division->id }}">{{ $division->name }}</option>
    @endforeach
</select>
