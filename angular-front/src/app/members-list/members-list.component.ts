import { Component, Input, ViewChild, AfterViewInit } from '@angular/core';
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { ApiMembersSortedByLastRegistrationDateGet200ResponseInner } from '../generated/api/model/apiMembersSortedByLastRegistrationDateGet200ResponseInner';

@Component({
  selector: 'app-members-list',
  standalone: true,
  imports: [MatTableModule, MatSortModule],
  templateUrl: './members-list.component.html',
  styleUrl: './members-list.component.css'
})
export class MembersListComponent implements AfterViewInit {
	@Input() members: Array<ApiMembersSortedByLastRegistrationDateGet200ResponseInner> = [];
	membersDataSource: MatTableDataSource<ApiMembersSortedByLastRegistrationDateGet200ResponseInner> = new MatTableDataSource(this.members);

	displayedColumns: string[] = ['lastRegistrationDate', 'firstName'];

	@ViewChild(MatSort) sort!: MatSort;

	ngAfterViewInit() {
		this.membersDataSource = new MatTableDataSource(this.members);
		this.membersDataSource.sort = this.sort;
	}

}
