<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f7f7f7;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,.08);
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        a.button, button {
            background: #111827;
            color: #fff;
            padding: 10px 14px;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        a.button:hover, button:hover {
            opacity: .9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        table th {
            background: #f3f4f6;
        }
        .actions {
            display: flex;
            gap: 8px;
        }
        .btn-edit {
            background: #2563eb;
        }
        .btn-show {
            background: #059669;
        }
        .btn-delete {
            background: #dc2626;
        }
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
        }
        .empty {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        .pagination {
            margin-top: 20px;
        }
        form {
            display: inline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <h1>Listado de Categorías {{$minombre}}</h1>
            <a href="{{ route('categorias.create') }}" class="button">Nueva categoría</a>
        </div>

        @if (session('success'))
            <div class="alert">
                {{ session('success') }}
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Fecha creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categorias as $categoria)
                    <tr>
                        <td>{{ $categoria->id }}</td>
                        <td>{{ $categoria->nombre }}</td>
                        <td>{{ $categoria->descripcion ?? 'Sin descripción' }}</td>
                        <td>{{ $categoria->estado ? 'Activa' : 'Inactiva' }}</td>
                        <td>{{ $categoria->created_at ? $categoria->created_at->format('d/m/Y H:i') : 'N/A' }}</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('categorias.show', $categoria->id) }}" class="button btn-show">Ver</a>
                                <a href="{{ route('categorias.edit', $categoria->id) }}" class="button btn-edit">Editar</a>

                                <form action="{{ route('categorias.destroy', $categoria->id) }}" method="POST" onsubmit="return confirm('¿Deseas eliminar esta categoría?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-delete">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">No hay categorías registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $categorias->links() }}
        </div>
    </div>
</body>
</html>