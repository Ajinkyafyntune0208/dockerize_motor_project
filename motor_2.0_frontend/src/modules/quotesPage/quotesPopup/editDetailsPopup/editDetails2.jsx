import React, { useState, useEffect } from "react";
import PropTypes from "prop-types";
import { useForm, Controller } from "react-hook-form";
import { yupResolver } from "@hookform/resolvers/yup";
import { useDispatch, useSelector } from "react-redux";
import { useLocation } from "react-router";
import { useHistory } from "react-router-dom";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import Drawer from "@mui/material/Drawer";
import _ from "lodash";
import moment from "moment";
import Popup from "components/Popup/Popup";
import "../policyTypePopup/policyTypePopup.css";
import * as yup from "yup";
import * as restStyle from "./styles";
import { carrierType } from "./helper";
import { EditDetailsTop } from "./VehicleDetails/editDetailsTop";
import { VehicleDetails } from "./VehicleDetails/VehicleDetails";
import { FuelType as FuelSources } from "modules/Home/steps/car-details/helper";
//prettier-ignore
import { clear as clearQuote, setQuotesList, SaveAddonsData,
         error as quotesError,
       } from "../../quote.slice";
//prettier-ignore
import { set_temp_data, Variant as VariantType, getFuelType,
         getFuel as setFuelType, BrandType, ModelType,
         Rto, modelType as emptyModelType, variant as emptyVariant,
       } from "modules/Home/home.slice";
import ThemeObj from "modules/theme-config/theme-config";
import { CancelAll, clear } from "modules/quotesPage/quote.slice";
//prettier-ignore
import { setTempData, saveQuoteData, error } from "../../filterConatiner/quoteFilter.slice";

// prettier-ignore
const { GlobalStyle, MobileDrawerBody2, CloseButton,  
        ContentWrap, ContentBox, UpdateButton, PremChangeWarning
      } = restStyle;

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme1 = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

//---------------------yup validate for man date and variant----------------------------

const yupValidate = yup.object({
  variant: yup.string("Variant is required").required("Variant is required"),
});

const EditInfoPopup2 = ({ show, onClose, type, TypeReturn }) => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const { register, handleSubmit, errors, setValue, watch, control } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });
  const location = useLocation();
  const history = useHistory();
  const dispatch = useDispatch();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const journey_type = query.get("journey_type");
  const shared = query.get("shared");
  const FuelSelected = watch("fuel");
  const PrevIcSelected = watch("preIc");
  const GcvCarrierType = watch("carrierType");
  const versionSelected = watch("variant");
  const brand = watch("brand");
  const model = watch("model");
  const rtoValue = watch("rto");
  const newManDate = watch("date2");
  const { tempData, prevInsList } = useSelector((state) => state.quoteFilter);
  const typeId = query.get("typeid");
  //prettier-ignore
  const { temp_data, variant: varnt, getFuel,
          brandType, modelType, rto,
        } = useSelector((state) => state.home);

  let userAgent = navigator.userAgent;
  let isMobileIOS = false; //initiate as false
  // device detection
  if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream && lessthan767) {
    isMobileIOS = true;
  }

  //clearing old fuel data
  useEffect(() => {
    model && dispatch(setFuelType([]));
  }, [model]);

  //fetching fuel types
  useEffect(() => {
    if (temp_data?.productSubTypeId && temp_data?.modelId)
      model &&
        dispatch(
          getFuelType(
            {
              modelId: model?.id,
              productSubTypeId: temp_data?.productSubTypeId,
            },
            true
          )
        );
  }, [model]);

  //setting manufacture date
  const [manufactureDate, setManfactureDate] = useState(false);
  useEffect(() => {
    if (newManDate) {
      setManfactureDate(`01-${newManDate}`);
    }
  }, [newManDate]);

  //Cancel process when editied
  const handleEdit = () => {
    dispatch(CancelAll(true));
    dispatch(clear());
    dispatch(quotesError(null));
    dispatch(error(null));
    dispatch(setQuotesList([]));
    dispatch(clearQuote());
    dispatch(saveQuoteData(null));
    dispatch(
      setTempData({
        policyType: false,
      })
    );
    dispatch(
      set_temp_data({
        newCar: false,
        breakIn: false,
        leadJourneyEnd: false,
      })
    );

    history.push(
      `/${type}/registration?enquiry_id=${enquiry_id}${
        token ? `&xutm=${token}` : ``
      }${typeId ? `&typeid=${typeId}` : ``}${
        journey_type ? `&journey_type=${journey_type}` : ``
      }${shared ? `&shared=${shared}` : ``}`
    );
    var data1 = {
      enquiryId: temp_data?.enquiry_id || enquiry_id,
      addonData: {
        addons: null,
        accessories: null,
        discounts: null,
        additional_covers: null,
      },
    };

    dispatch(SaveAddonsData(data1));
  };

  const onSubmit = () => {
    dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
    let today = moment().format("DD-MM-YYYY");
    let a = temp_data?.expiry;
    let b = moment().format("DD-MM-YYYY");
    let data = {
      manfId: brand?.id,
      manfName: brand?.name,
      manfactureId: brand?.id,
      manfactureName: brand?.name,
      modelId: model?.id,
      modelName: model?.name,
      model: model?.id,
      rtoNumber: rtoValue?.value,
      rto: rtoValue?.value,
    };
    dispatch(
      set_temp_data({
        gcvCarrierType: GcvCarrierType?.id,
        fuel: versionSelected?.fuelFype || FuelSelected?.value,
        versionId: versionSelected?.id,
        versionName: versionSelected?.name,
        selectedGvw: versionSelected?.grosssVehicleWeight,
        defaultGvw: versionSelected?.grosssVehicleWeight,
        prevIc: prevInsList.filter(
          (i) => i.previousInsurer === PrevIcSelected?.name
        )[0]?.companyAlias,
        prevIcFullName: PrevIcSelected?.name,
        rtoCode: rtoValue?.value,
        rtoCity: rtoValue?.rtoName,
        ...data,
      })
    );
    dispatch(
      setTempData({
        idvChoosed: 0,
        idvType: "lowIdv",
        ...data,
      })
    );
    dispatch(CancelAll(false)); // resetting cancel all apis loading so quotes will restart (quotes apis)
    onClose(false);
  };

  //Fetching brand data
  useEffect(() => {
    if (temp_data?.productSubTypeId) {
      dispatch(
        BrandType({ productSubTypeId: temp_data?.productSubTypeId, enquiryId: enquiry_id }, true)
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data.productSubTypeId]);

  //load Model Data
  useEffect(() => {
    if (temp_data?.productSubTypeId && temp_data?.manfId) {
      brand &&
        dispatch(
          ModelType(
            {
              productSubTypeId: temp_data?.productSubTypeId,
              manfId: brand?.id || temp_data?.manfId,
            },
            false,
            true
          )
        );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [brand]);

  //Fetching variant data
  useEffect(() => {
    if (temp_data?.modelId && FuelSelected?.value) {
      FuelSelected &&
        dispatch(
          VariantType(
            {
              productSubTypeId: temp_data?.productSubTypeId,
              modelId: model?.id || temp_data?.modelId,
              fuelType: FuelSelected?.value || temp_data?.fuel,
              LpgCngKitValue: temp_data?.kit_val ? temp_data?.kit_val : null,
            },
            true
          )
        );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [FuelSelected]);

  //get rto
  useEffect(() => {
    if (
      !temp_data?.regNo ||
      temp_data?.regNo === "NEW" ||
      (temp_data?.regNo && temp_data?.regNo[0] * 1)
    ) {
      dispatch(Rto({}, true));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.rtoNumber]);

  //prefill
  useEffect(() => {
    if (temp_data?.rtoNumber) {
      const filtered_data = !_.isEmpty(rto)
        ? rto?.filter(({ rtoNumber }, index) => {
            return rtoNumber === temp_data?.rtoNumber;
          })
        : [];
      let selected_option = [
        {
          rtoNumber: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoNumber,
          rtoId: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoId,
          stateName: !_.isEmpty(filtered_data) && filtered_data[0]?.stateName,
          rtoName: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoName,
          label:
            !_.isEmpty(filtered_data) &&
            `${filtered_data[0]?.rtoNumber?.replace(/-/g, "")} (${
              filtered_data[0]?.stateName
            } : ${filtered_data[0]?.rtoName})`,
          name:
            !_.isEmpty(filtered_data) &&
            `${filtered_data[0]?.rtoNumber?.replace(/-/g, "")} (${
              filtered_data[0]?.stateName
            } : ${filtered_data[0]?.rtoName})`,
          value: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoNumber,
          id: !_.isEmpty(filtered_data) && filtered_data[0]?.rtoId,
        },
      ];
      !_.isEmpty(selected_option) && setValue("rto", selected_option[0]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data, rto]);

  ///-------------creating options for variant from api response  -----------------------------

  const Options = !_.isEmpty(varnt)
    ? varnt?.map(
        ({
          versionId,
          versionName,
          cubicCapacity,
          grosssVehicleWeight,
          kw,
          fuelFype,
          vehicleBuiltUp,
        }) => ({
          label:
            temp_data?.parent?.productSubTypeCode !== "GCV"
              ? `${versionName}${
                  vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
                }${
                  fuelFype === "ELECTRIC"
                    ? kw
                      ? TypeReturn(type) !== "bike"
                        ? ` - ${kw}kW`
                        : ""
                      : ""
                    : cubicCapacity
                    ? TypeReturn(type) !== "bike"
                      ? ` - ${cubicCapacity}CC`
                      : ""
                    : ""
                }`
              : `${versionName}${
                  vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
                }${grosssVehicleWeight ? ` - ${grosssVehicleWeight}GVW` : ""}`,
          name:
            temp_data?.parent?.productSubTypeCode !== "GCV"
              ? `${versionName}${
                  vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
                }${
                  fuelFype === "ELECTRIC"
                    ? kw
                      ? TypeReturn(type) !== "bike"
                        ? ` - ${kw}kW`
                        : ""
                      : ""
                    : cubicCapacity
                    ? TypeReturn(type) !== "bike"
                      ? ` - ${cubicCapacity}CC`
                      : ""
                    : ""
                }`
              : `${versionName}${
                  vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
                }${grosssVehicleWeight ? ` - ${grosssVehicleWeight}GVW` : ""}`,
          id: versionId,
          value: versionId,
          fuelFype: fuelFype,
          grosssVehicleWeight: grosssVehicleWeight,
        })
      )
    : [];

  const Brands = !_.isEmpty(brandType)
    ? brandType?.map(({ manfName, manfId }) => ({
        label: manfName,
        name: manfName,
        id: manfId,
        value: manfName,
      }))
    : [];

  const Models = !_.isEmpty(modelType)
    ? modelType?.map(({ modelName, modelId }) => ({
        label: modelName,
        name: modelName,
        id: modelId,
        value: modelName,
      }))
    : [];

  const RTO = !_.isEmpty(rto)
    ? rto?.map(({ rtoName, rtoNumber, rtoId, stateName }) => ({
        label: `${rtoNumber?.replace(/-/g, "")} - (${stateName} : ${rtoName})`,
        name: `${rtoNumber?.replace(/-/g, "")} - (${stateName} : ${rtoName})`,
        id: rtoId,
        value: rtoNumber,
        rtoName: rtoName,
      }))
    : [];

  //Formatting fuel types
  const availableTypes = !_.isEmpty(getFuel)
    ? getFuel.map((item) => item.toUpperCase())
    : [];
  const Fuel = _.compact(FuelSources(availableTypes));

  //setting manf date and reg date
  useEffect(() => {
    if (temp_data?.manfDate) setValue("date2", temp_data?.manfDate);
    if (temp_data?.regDate) setValue("date1", temp_data?.regDate);
  }, [temp_data]);

  //Multiselect brand
  useEffect(() => {
    if (temp_data?.versionId && !_.isEmpty(brandType)) {
      let check = brandType?.filter(
        ({ manfName }) => manfName === temp_data?.manfName
      );
      let selected_option = check?.map(({ manfId, manfName }) => {
        return {
          id: manfId,
          value: manfName,
          label: temp_data?.manfName,
          name: temp_data?.manfName,
        };
      });

      !_.isEmpty(selected_option) && setValue("brand", selected_option[0]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.manfId, brandType]);

  //Multiselect model
  useEffect(() => {
    let check = modelType?.filter(
      ({ modelName }) => modelName === temp_data?.modelName
    );
    let selected_option = check?.map(({ modelId, modelName }) => {
      return {
        id: modelId,
        value: modelName,
        label: temp_data?.modelName,
        name: temp_data?.modelName,
      };
    });

    !_.isEmpty(selected_option) && setValue("model", selected_option[0]);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.manfId, modelType]);

  //Multiselect variant
  useEffect(() => {
    if (temp_data?.versionId && !_.isEmpty(varnt)) {
      let check = varnt?.filter(
        ({ versionId }) =>
          Number(versionId) === Number(temp_data?.versionId) ||
          versionId === temp_data?.versionId
      );
      let selected_option = check?.map(
        ({
          versionId,
          versionName,
          cubicCapacity,
          grosssVehicleWeight,
          kw,
          fuelFype,
          vehicleBuiltUp,
        }) => {
          return {
            id: versionId,
            value: versionId,
            label:
              temp_data?.parent?.productSubTypeCode !== "GCV"
                ? `${versionName}${
                    vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
                  }${
                    fuelFype === "ELECTRIC"
                      ? kw
                        ? ` - ${kw}kW`
                        : ""
                      : cubicCapacity
                      ? ` - ${cubicCapacity}CC`
                      : ""
                  }`
                : `${versionName}${
                    vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
                  }${
                    grosssVehicleWeight ? ` - ${grosssVehicleWeight}GVW` : ""
                  }`,
            name:
              temp_data?.parent?.productSubTypeCode !== "GCV"
                ? `${versionName}${
                    vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
                  }${
                    fuelFype === "ELECTRIC"
                      ? kw
                        ? ` - ${kw}kW`
                        : ""
                      : cubicCapacity
                      ? ` - ${cubicCapacity}CC`
                      : ""
                  }`
                : `${versionName}${
                    vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
                  }${
                    grosssVehicleWeight ? ` - ${grosssVehicleWeight}GVW` : ""
                  }`,
            fuelFype: fuelFype,
            grosssVehicleWeight: grosssVehicleWeight,
          };
        }
      );

      !_.isEmpty(selected_option) && setValue("variant", selected_option[0]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.versionId, varnt]);

  //Fetching and setting carrier data
  useEffect(() => {
    if (temp_data?.gcvCarrierType) {
      let check = carrierType?.filter(
        ({ id }) => id === temp_data?.gcvCarrierType
      );
      let selected_option = check?.map(({ id }) => {
        return {
          id: id,
          value: id,
          label: id,
          name: id,
        };
      });

      !_.isEmpty(selected_option) &&
        setValue("carrierType", selected_option[0]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.gcvCarrierType]);

  //Multiselect fuel
  useEffect(() => {
    if (
      temp_data?.fuel &&
      !_.isEmpty(Fuel) &&
      !FuelSelected &&
      !_.isEmpty(brand) &&
      !_.isEmpty(model)
    ) {
      let check = Fuel?.filter(({ value }) =>
        temp_data?.fuel === "LPG"
          ? value === "CNG"
          : value?.toLowerCase() === temp_data?.fuel?.toLowerCase()
      );
      let selected_option = check?.map(({ label, value, logo }) => {
        return {
          name: label,
          label: label,
          value: value,
          id: value,
          logo: logo,
        };
      });
      !_.isEmpty(selected_option) && setValue("fuel", selected_option[0]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data, Fuel, brand, model]);

  //Previous IC
  const [prevIcData, setPrevIcData] = useState(false);
  useEffect(() => {
    if (
      temp_data?.prevIc &&
      !temp_data?.newCar &&
      temp_data?.prevIc !== "NEW" &&
      temp_data?.prevIc !== "New"
    ) {
      setPrevIcData(true);
    } else {
      setPrevIcData(false);
    }
  }, [temp_data?.prevIc]);

  useEffect(() => {
    if (temp_data?.prevIcFullName) {
      let check = prevInsList?.filter(
        ({ previousInsurer }) => previousInsurer === temp_data?.prevIcFullName
      );
      let selected_option = check?.map(({ previousInsurer }) => {
        return {
          label: previousInsurer,
          name: previousInsurer,
          value: previousInsurer,
          id: previousInsurer,
        };
      });

      !_.isEmpty(selected_option) && setValue("preIc", selected_option[0]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.prevIcFullName]);

  //Mobile drawer
  const [drawer, setDrawer] = useState(false);

  useEffect(() => {
    if (lessthan767 && show) {
      setTimeout(() => {
        setDrawer(true);
      }, 50);
    }
  }, [show]);

  const content = (
    <>
      <ContentWrap isMobileIOS={isMobileIOS}>
        <ContentBox>
          <EditDetailsTop
            lessthan767={lessthan767}
            TypeReturn={TypeReturn}
            type={type}
            temp_data={temp_data}
            token={token}
            query={query}
            handleEdit={handleEdit}
          />

          <VehicleDetails
            temp_data={temp_data}
            Controller={Controller}
            control={control}
            register={register}
            carrierType={carrierType}
            errors={errors}
            Brands={Brands}
            dispatch={dispatch}
            emptyModelType={emptyModelType}
            emptyVariant={emptyVariant}
            setFuelType={setFuelType}
            setValue={setValue}
            Fuel={Fuel}
            RTO={RTO}
            Models={Models}
            model={model}
            Options={Options}
          />
        </ContentBox>
        <PremChangeWarning>
          <div className="ncb_msg">
            <div className="image"></div>
            <p
              className="messagetxt"
              style={{ fontSize: "15px", fontWeight: "800" }}
            >
              {"Your premium will be updated based on your changes"}
              <b></b>.
            </p>
          </div>
        </PremChangeWarning>
        <UpdateButton onClick={handleSubmit(onSubmit)}>Update</UpdateButton>
      </ContentWrap>
    </>
  );

  return !lessthan767 ? (
    <Popup
      height={lessthan767 ? "100%" : "auto"}
      width={lessthan767 ? "100%" : "700px"}
      top="40%"
      show={show}
      onClose={onClose}
      content={content}
      position="middle"
      hiddenClose={tempData?.policyType ? false : true}
    />
  ) : (
    <>
      <React.Fragment key={"bottom"} style={{ borderRadius: "5% 5% 0% 0%" }}>
        <Drawer
          anchor={"bottom"}
          open={drawer}
          onClose={() => {
            setDrawer(false);
            onClose(false);
          }}
          onOpen={() => setDrawer(true)}
          ModalProps={{
            keepMounted: true,
          }}
          style={{ overflowX: isMobileIOS && "hidden !important" }}
        >
          <MobileDrawerBody2 isMobileIOS={isMobileIOS}>
            <CloseButton
              onClick={() => {
                setDrawer(false);
                onClose(false);
              }}
            >
              <svg
                version="1.1"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                style={{ height: " 25px" }}
              >
                <path
                  fill={"#000"}
                  d="M12,2c-5.53,0 -10,4.47 -10,10c0,5.53 4.47,10 10,10c5.53,0 10,-4.47 10,-10c0,-5.53 -4.47,-10 -10,-10Zm5,13.59l-1.41,1.41l-3.59,-3.59l-3.59,3.59l-1.41,-1.41l3.59,-3.59l-3.59,-3.59l1.41,-1.41l3.59,3.59l3.59,-3.59l1.41,1.41l-3.59,3.59l3.59,3.59Z"
                ></path>
                <path fill="none" d="M0,0h24v24h-24Z"></path>
              </svg>
            </CloseButton>
            {content}
          </MobileDrawerBody2>
        </Drawer>
      </React.Fragment>
      <GlobalStyle />
    </>
  );
};

// PropTypes
EditInfoPopup2.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
EditInfoPopup2.defaultProps = {
  show: false,
  onClose: () => {},
};

export default EditInfoPopup2;
