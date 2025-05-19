import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TotpActivatorComponent } from './totp-activator.component';

describe('TotpActivatorComponent', () => {
  let component: TotpActivatorComponent;
  let fixture: ComponentFixture<TotpActivatorComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TotpActivatorComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(TotpActivatorComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
