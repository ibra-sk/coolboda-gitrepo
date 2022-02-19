window.dataTable = function () {
	return {
		items: [],
		view: 10,
		searchInput: '',
		pages: [],
		offset: 10,
		pagination: {
			total: dati.length,
			lastPage: Math.ceil(dati.length / 10),
			perPage: 10,
			currentPage: 1,
			from: 1,
			to: 1 * 10
		},
		currentPage: 1,
		sorted: {
			field: 'fullname',
			rule: 'desc'
		},
		initData() {
			this.items = dati.sort(this.compareOnKey('fullname', 'desc'))
			this.showPages()
		},
		compareOnKey(key, rule) {
			return function(a, b) { 
				if (key === 'reference' || key === 'fullname') {
					let comparison = 0
					const fieldA = a[key].toUpperCase();
					const fieldB = b[key].toUpperCase();
					if (rule === 'desc') {
						if (fieldA > fieldB) {
							comparison = 1;
						} else if (fieldA < fieldB) {
							comparison = -1;
						}
					} else {
						if (fieldA < fieldB) {
							comparison = 1;
						} else if (fieldA > fieldB) {
							comparison = -1;
						}
					}
					return comparison
				} else {
					if (rule === 'desc') {
						return a.year - b.year
					} else {
						return b.year - a.year
					}
				}
			}
		},
		checkView(index) {
			return index > this.pagination.to || index < this.pagination.from ? false : true
		},
		checkPage(item) {
			if (item <= this.currentPage + 10) {
				return true
			}
			return false
		},
		search(value) {
			if (value.length > 1) {
				const options = {
					shouldSort: true,
					keys: ['timestamp', 'fullname', 'reference', 'boda', 'phone'],
					threshold: 0
				}                
				const fuse = new Fuse(dati, options)
				this.items = fuse.search(value).map(elem => elem.item)
			} else {
				this.items = dati
			}
			// console.log(this.items.length)
      
			this.changePage(1)
			this.showPages()
		},
		sort(field, rule) {
			this.items = this.items.sort(this.compareOnKey(field, rule))
			this.sorted.field = field
			this.sorted.rule = rule
		},
		changePage(page) {
			if (page >= 1 && page <= this.pagination.lastPage) {
				this.currentPage = page
				const total = this.items.length
				const lastPage = Math.ceil(total / this.view) || 1
				const from = (page - 1) * this.view + 1
				let to = page * this.view
				if (page === lastPage) {
					to = total
				}
				this.pagination.total = total
				this.pagination.lastPage = lastPage
				this.pagination.perPage = this.view
				this.pagination.currentPage = page
				this.pagination.from = from
				this.pagination.to = to
				this.showPages()
			}
		},
		showPages() {
			const pages = []
			let from = this.pagination.currentPage - Math.ceil(this.offset / 2)
			if (from < 1) {
				from = 1
			}
			let to = from + this.offset - 1
			if (to > this.pagination.lastPage) {
				to = this.pagination.lastPage
			}
			while (from <= to) {
				pages.push(from)
				from++
			}
			this.pages = pages
		},
		changeView() {
			this.changePage(1)
			this.showPages()
		},
		isEmpty() {
			return this.pagination.total ? false : true
		}
	}
}