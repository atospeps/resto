UPDATE resto.keywords SET value=normalize(value) WHERE TYPE IN ('continent', 'country', 'region', 'state', 'landuse');
