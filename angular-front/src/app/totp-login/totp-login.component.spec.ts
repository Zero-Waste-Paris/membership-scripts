import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TotpLoginComponent } from './totp-login.component';

describe('TotpComponent', () => {
  let component: TotpLoginComponent;
  let fixture: ComponentFixture<TotpLoginComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TotpLoginComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(TotpLoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
