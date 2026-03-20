<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Logbook</title>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --brand: #4f46e5;
            --brand-dark: #4338ca;
            --ok-bg: #ecfdf5;
            --ok-text: #047857;
            --err-bg: #fef2f2;
            --err-text: #b91c1c;
            --info-bg: #eff6ff;
            --info-text: #1d4ed8;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(160deg, #eef2ff 0%, var(--bg) 40%, #f1f5f9 100%);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .wrap {
            max-width: 760px;
            margin: 0 auto;
            padding: 18px 14px 28px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05);
            padding: 14px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .status {
            margin-top: 10px;
            display: inline-block;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            border: 1px solid var(--border);
        }

        .status.in-use {
            color: #92400e;
            background: #fffbeb;
            border-color: #fde68a;
        }

        .status.ready {
            color: #065f46;
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .alert {
            border-radius: 10px;
            padding: 10px 12px;
            margin-top: 12px;
            font-size: 13px;
        }

        .ok { background: var(--ok-bg); color: var(--ok-text); }
        .err { background: var(--err-bg); color: var(--err-text); }
        .info { background: var(--info-bg); color: var(--info-text); }

        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 14px;
        }

        @media (min-width: 640px) {
            .grid.two { grid-template-columns: 1fr 1fr; }
            .span-2 { grid-column: span 2; }
        }

        label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 5px;
        }

        input, select, textarea, button {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-size: 14px;
            padding: 10px 11px;
            background: #fff;
        }

        textarea { resize: vertical; min-height: 76px; }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #a5b4fc;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
        }

        .btn {
            margin-top: 12px;
            background: var(--brand);
            color: #fff;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }

        .btn:hover { background: var(--brand-dark); }

        .hint { margin-top: 10px; color: var(--muted); font-size: 12px; }

        .pin-input {
            text-align: center;
            letter-spacing: 0.25em;
            font-weight: 700;
        }

        .trip-meta {
            margin-top: 12px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #f8fafc;
        }

        .trip-meta-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 6px;
            font-size: 12px;
        }

        @media (min-width: 640px) {
            .trip-meta-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .trip-meta strong {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            margin-right: 4px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1 class="title">Vehicle Trip Cycle</h1>
            <p class="subtitle">
                {{ $vehicle->brand }} {{ $vehicle->model }}
                ({{ $vehicle->vehicle->plate_number ?? '-' }})
            </p>

            @if($activeTrip)
                <span class="status in-use">Trip In Use: End Trip Required</span>
            @else
                <span class="status ready">Ready: Start Trip</span>
            @endif

            @if(session('success'))
                <div class="alert ok">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert err">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if($pinRequired)
                <div class="alert info">
                    Access PIN is required. Temporary PIN: <strong>{{ $temporaryPin }}</strong>
                </div>
            @endif

            @if(!$activeTrip)
                <form method="POST" action="{{ route('public.vehicles.logbook.start', $vehicle->public_uuid) }}">
                    @csrf
                    <div class="grid two">
                        @if($pinRequired)
                            <div>
                                <label for="access_pin">Access PIN</label>
                                <input
                                    id="access_pin"
                                    name="access_pin"
                                    type="password"
                                    autocomplete="off"
                                    inputmode="numeric"
                                    minlength="6"
                                    maxlength="6"
                                    pattern="[0-9]{6}"
                                    placeholder="6-digit PIN"
                                    class="pin-input"
                                    required
                                >
                                <div class="hint">Enter the 6-digit vehicle PIN.</div>
                            </div>
                        @endif

                        <div>
                            <label for="driver_name">Driver Name</label>
                            <input
                                id="driver_name"
                                name="driver_name"
                                type="text"
                                value="{{ old('driver_name') }}"
                                placeholder="Driver name"
                                required
                            >
                        </div>

                        <div>
                            <label for="start_mileage">Start Mileage</label>
                            <input
                                id="start_mileage"
                                name="start_mileage"
                                type="number"
                                min="0"
                                step="0.1"
                                value="{{ old('start_mileage') }}"
                                required
                            >
                        </div>

                        <div>
                            <label for="bound_to_type">Bound To</label>
                            <select id="bound_to_type" name="bound_to_type" onchange="toggleBoundFields(this.value)">
                                <option value="office" @selected(old('bound_to_type', 'office') === 'office')>Office</option>
                                <option value="project" @selected(old('bound_to_type') === 'project')>Project</option>
                                <option value="others" @selected(old('bound_to_type') === 'others')>Others</option>
                            </select>
                        </div>

                        <div id="projectWrap" style="display:none;">
                            <label for="bound_to_project_id">Project</label>
                            <select id="bound_to_project_id" name="bound_to_project_id">
                                <option value="">Select project</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('bound_to_project_id') === (string) $project->id)>
                                        {{ $project->code }} - {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="othersWrap" style="display:none;">
                            <label for="bound_to_label">Specify</label>
                            <input
                                id="bound_to_label"
                                name="bound_to_label"
                                type="text"
                                value="{{ old('bound_to_label') }}"
                                placeholder="Example: Supplier warehouse"
                            >
                        </div>

                        <div class="span-2">
                            <label for="origin">Origin</label>
                            <input
                                id="origin"
                                name="origin"
                                type="text"
                                value="{{ old('origin') }}"
                                placeholder="Where does the trip start from?"
                                required
                            >
                        </div>

                        <div class="span-2">
                            <label for="destination">Destination</label>
                            <input
                                id="destination"
                                name="destination"
                                type="text"
                                value="{{ old('destination') }}"
                                placeholder="Where is the vehicle going?"
                                required
                            >
                        </div>

                        <div class="span-2">
                            <label for="purpose">Purpose (optional)</label>
                            <textarea
                                id="purpose"
                                name="purpose"
                                placeholder="Reason for the trip"
                            >{{ old('purpose') }}</textarea>
                        </div>
                    </div>

                    <button class="btn" type="submit">Start Trip</button>
                    <div class="hint">After finish using vehicle, return here and submit End Trip mileage.</div>
                </form>
            @else
                <div class="trip-meta">
                    <div class="trip-meta-grid">
                        <div><strong>Started:</strong> {{ optional($activeTrip->started_at)->format('Y-m-d H:i') ?? '-' }}</div>
                        <div><strong>Driver:</strong> {{ $activeTrip->driver_name }}</div>
                        <div><strong>Start Mileage:</strong> {{ $activeTrip->start_mileage ?? '-' }}</div>
                        <div><strong>Bound To:</strong> {{ $activeTrip->bound_to_label ?? '-' }}</div>
                        <div><strong>Origin:</strong> {{ $activeTrip->origin ?? '-' }}</div>
                        <div><strong>Planned Destination:</strong> {{ $activeTrip->destination ?? '-' }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('public.vehicles.logbook.end', $vehicle->public_uuid) }}">
                    @csrf
                    <input type="hidden" name="log_id" value="{{ $activeTrip->id }}">

                    <div class="grid two">
                        @if($pinRequired)
                            <div>
                                <label for="end_access_pin">Access PIN</label>
                                <input
                                    id="end_access_pin"
                                    name="access_pin"
                                    type="password"
                                    autocomplete="off"
                                    inputmode="numeric"
                                    minlength="6"
                                    maxlength="6"
                                    pattern="[0-9]{6}"
                                    placeholder="6-digit PIN"
                                    class="pin-input"
                                    required
                                >
                            </div>
                        @endif

                        <div>
                            <label for="end_driver_name">Driver Name</label>
                            <input
                                id="end_driver_name"
                                name="driver_name"
                                type="text"
                                value="{{ old('driver_name', $activeTrip->driver_name) }}"
                                required
                            >
                        </div>

                        <div>
                            <label for="end_mileage">End Mileage</label>
                            <input
                                id="end_mileage"
                                name="end_mileage"
                                type="number"
                                min="0"
                                step="0.1"
                                value="{{ old('end_mileage') }}"
                                required
                            >
                        </div>

                        <div class="span-2">
                            <label for="end_destination">Final Destination</label>
                            <input
                                id="end_destination"
                                name="destination"
                                type="text"
                                value="{{ old('destination', $activeTrip->destination) }}"
                                required
                            >
                        </div>

                        <div class="span-2">
                            <label for="end_purpose">Trip Note (optional)</label>
                            <textarea
                                id="end_purpose"
                                name="purpose"
                                placeholder="Optional update or end-trip note"
                            >{{ old('purpose', $activeTrip->purpose) }}</textarea>
                        </div>
                    </div>

                    <button class="btn" type="submit">End Trip</button>
                    <div class="hint">End trip updates mileage and closes driver accountability for this cycle.</div>
                </form>
            @endif
        </div>
    </div>

    <script>
        function toggleBoundFields(type) {
            const projectWrap = document.getElementById('projectWrap');
            const othersWrap = document.getElementById('othersWrap');
            if (projectWrap) {
                projectWrap.style.display = type === 'project' ? 'block' : 'none';
            }
            if (othersWrap) {
                othersWrap.style.display = type === 'others' ? 'block' : 'none';
            }
        }

        toggleBoundFields('{{ old('bound_to_type', 'office') }}');

        document.querySelectorAll('.pin-input').forEach((pinInput) => {
            pinInput.addEventListener('input', () => {
                pinInput.value = pinInput.value.replace(/\D/g, '').slice(0, 6);
            });
        });
    </script>
</body>
</html>
